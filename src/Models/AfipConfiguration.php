<?php

declare(strict_types=1);

namespace Resguar\AfipSdk\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modelo para la configuración de AFIP
 *
 * Almacena la configuración necesaria para conectarse con AFIP
 */
class AfipConfiguration extends Model
{
    use SoftDeletes;
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'afip_configurations';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'cuit',
        'environment',
        'certificate_path',
        'key_path',
        'certificate_password',
        'is_active',
        'description',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Obtiene los puntos de venta asociados a esta configuración
     *
     * @return HasMany
     */
    public function pointOfSales(): HasMany
    {
        return $this->hasMany(PointOfSale::class, 'afip_configuration_id');
    }

    /**
     * Obtiene la configuración activa
     *
     * @return static|null
     */
    public static function getActive(): ?static
    {
        return static::where('is_active', true)->first();
    }
}

