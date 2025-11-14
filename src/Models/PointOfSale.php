<?php

declare(strict_types=1);

namespace Resguar\AfipSdk\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modelo para los puntos de venta de AFIP
 *
 * Almacena información sobre los puntos de venta habilitados
 */
class PointOfSale extends Model
{
    use SoftDeletes;
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'point_of_sales';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'afip_configuration_id',
        'number',
        'name',
        'blocking_date',
        'is_active',
        'description',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'number' => 'integer',
        'blocking_date' => 'date',
        'is_active' => 'boolean',
    ];

    /**
     * Obtiene la configuración de AFIP asociada
     *
     * @return BelongsTo
     */
    public function afipConfiguration(): BelongsTo
    {
        return $this->belongsTo(AfipConfiguration::class, 'afip_configuration_id');
    }
}

