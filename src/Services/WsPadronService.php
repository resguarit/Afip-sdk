<?php

declare(strict_types=1);

namespace Resguar\AfipSdk\Services;

use Illuminate\Support\Facades\Log;
use Resguar\AfipSdk\Exceptions\AfipException;
use SoapClient;
use SoapFault;

/**
 * Servicio para consultar el Padrón de AFIP (ws_sr_padron_a13)
 *
 * Permite obtener información de contribuyentes:
 * - Datos fiscales
 * - Condición de IVA
 * - Actividades económicas
 * - Impuestos inscriptos
 */
class WsPadronService
{
    private const WSDL_PRODUCTION = 'https://aws.afip.gov.ar/sr-padron/webservices/personaServiceA13?WSDL';
    private const WSDL_TESTING = 'https://awshomo.afip.gov.ar/sr-padron/webservices/personaServiceA13?WSDL';
    private const SERVICE_NAME = 'ws_sr_padron_a13';

    /**
     * Mapeo de condiciones de IVA a tipos de comprobantes habilitados
     */
    private const IVA_RECEIPT_TYPES = [
        // Responsable Inscripto
        1 => [
            ['id' => 1, 'description' => 'Factura A'],
            ['id' => 2, 'description' => 'Nota de Débito A'],
            ['id' => 3, 'description' => 'Nota de Crédito A'],
            ['id' => 4, 'description' => 'Recibo A'],
            ['id' => 6, 'description' => 'Factura B'],
            ['id' => 7, 'description' => 'Nota de Débito B'],
            ['id' => 8, 'description' => 'Nota de Crédito B'],
            ['id' => 9, 'description' => 'Recibo B'],
            ['id' => 51, 'description' => 'Factura M'],
            ['id' => 52, 'description' => 'Nota de Débito M'],
            ['id' => 53, 'description' => 'Nota de Crédito M'],
            ['id' => 54, 'description' => 'Recibo M'],
            ['id' => 201, 'description' => 'Factura de Crédito electrónica MiPyMEs (FCE) A'],
            ['id' => 202, 'description' => 'Nota de Débito electrónica MiPyMEs (FCE) A'],
            ['id' => 203, 'description' => 'Nota de Crédito electrónica MiPyMEs (FCE) A'],
            ['id' => 206, 'description' => 'Factura de Crédito electrónica MiPyMEs (FCE) B'],
            ['id' => 207, 'description' => 'Nota de Débito electrónica MiPyMEs (FCE) B'],
            ['id' => 208, 'description' => 'Nota de Crédito electrónica MiPyMEs (FCE) B'],
        ],
        // Monotributista
        4 => [
            ['id' => 11, 'description' => 'Factura C'],
            ['id' => 12, 'description' => 'Nota de Débito C'],
            ['id' => 13, 'description' => 'Nota de Crédito C'],
            ['id' => 15, 'description' => 'Recibo C'],
            ['id' => 211, 'description' => 'Factura de Crédito electrónica MiPyMEs (FCE) C'],
            ['id' => 212, 'description' => 'Nota de Débito electrónica MiPyMEs (FCE) C'],
            ['id' => 213, 'description' => 'Nota de Crédito electrónica MiPyMEs (FCE) C'],
        ],
        // IVA Exento
        4 => [
            ['id' => 11, 'description' => 'Factura C'],
            ['id' => 12, 'description' => 'Nota de Débito C'],
            ['id' => 13, 'description' => 'Nota de Crédito C'],
            ['id' => 15, 'description' => 'Recibo C'],
        ],
    ];

    /**
     * Descripciones de condiciones de IVA
     */
    private const IVA_CONDITIONS = [
        1 => 'IVA Responsable Inscripto',
        2 => 'IVA Responsable No Inscripto',
        3 => 'IVA No Responsable',
        4 => 'IVA Sujeto Exento',
        5 => 'Consumidor Final',
        6 => 'Responsable Monotributo',
        7 => 'Sujeto No Categorizado',
        8 => 'Proveedor del Exterior',
        9 => 'Cliente del Exterior',
        10 => 'IVA Liberado - Ley N° 19.640',
        11 => 'IVA Responsable Inscripto - Agente de Percepción',
        12 => 'Pequeño Contribuyente Eventual',
        13 => 'Monotributista Social',
        14 => 'Pequeño Contribuyente Eventual Social',
    ];

    public function __construct(
        private readonly WsaaService $wsaaService,
        private readonly string $environment,
        private readonly string $defaultCuit
    ) {
    }

    /**
     * Consulta los datos completos de un contribuyente
     *
     * @param string $cuitConsulta CUIT a consultar
     * @param string|null $cuitSolicitante CUIT que realiza la consulta (opcional)
     * @return array Datos del contribuyente
     * @throws AfipException
     */
    public function getPersona(string $cuitConsulta, ?string $cuitSolicitante = null): array
    {
        $cuitSolicitante = $cuitSolicitante ?? $this->defaultCuit;
        
        $this->log('info', "Consultando padrón para CUIT: {$cuitConsulta}");

        try {
            // Obtener token para el servicio de padrón
            $tokenResponse = $this->wsaaService->getToken(self::SERVICE_NAME, $cuitSolicitante);
            
            // Crear cliente SOAP
            $wsdl = $this->environment === 'production' ? self::WSDL_PRODUCTION : self::WSDL_TESTING;
            
            $client = new SoapClient($wsdl, [
                'soap_version' => SOAP_1_1,
                'trace' => true,
                'exceptions' => true,
                'stream_context' => stream_context_create([
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true,
                    ],
                ]),
            ]);

            // Llamar al método getPersona_v2
            $response = $client->getPersona([
                'token' => $tokenResponse->token,
                'sign' => $tokenResponse->signature,
                'cuitRepresentada' => $cuitSolicitante,
                'idPersona' => $cuitConsulta,
            ]);

            return $this->parsePersonaResponse($response);

        } catch (SoapFault $e) {
            $this->log('error', "Error SOAP al consultar padrón: {$e->getMessage()}");
            throw new AfipException(
                "Error al consultar padrón AFIP: {$e->getMessage()}",
                (int) $e->getCode(),
                $e
            );
        } catch (\Exception $e) {
            $this->log('error', "Error al consultar padrón: {$e->getMessage()}");
            throw new AfipException(
                "Error al consultar padrón: {$e->getMessage()}",
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * Obtiene los tipos de comprobantes habilitados para un CUIT
     *
     * @param string $cuit CUIT a consultar
     * @return array Lista de tipos de comprobantes habilitados
     * @throws AfipException
     */
    public function getReceiptTypesForCuit(string $cuit): array
    {
        $persona = $this->getPersona($cuit);
        
        $condicionIva = $persona['condicion_iva']['id'] ?? null;
        
        if ($condicionIva === null) {
            throw new AfipException("No se pudo determinar la condición de IVA del contribuyente");
        }

        // Obtener tipos de comprobantes basados en la condición de IVA
        $receiptTypes = self::IVA_RECEIPT_TYPES[$condicionIva] ?? [];
        
        // Si es Responsable Inscripto o Monotributista con régimen especial, puede tener más tipos
        // Por ahora usamos el mapeo estático

        return [
            'cuit' => $cuit,
            'razon_social' => $persona['razon_social'],
            'condicion_iva' => $persona['condicion_iva'],
            'receipt_types' => $receiptTypes,
        ];
    }

    /**
     * Parsea la respuesta del servicio de padrón A13
     */
    private function parsePersonaResponse($response): array
    {
        // Estructura del padrón A13: personaReturn.persona
        $personaReturn = $response->personaReturn ?? $response;
        
        if (isset($personaReturn->errorConstancia)) {
            throw new AfipException(
                "Error de AFIP: " . ($personaReturn->errorConstancia->error ?? 'Error desconocido')
            );
        }

        // Los datos están en personaReturn.persona
        $persona = $personaReturn->persona ?? $personaReturn;
        
        // Extraer condición de IVA basándose en el tipo de persona
        $condicionIvaId = null;
        $condicionIvaDesc = 'No determinada';
        
        $tipoPersona = $persona->tipoPersona ?? null;
        
        // Las personas jurídicas (SRL, SA, etc.) son Responsables Inscriptos
        if ($tipoPersona === 'JURIDICA') {
            $condicionIvaId = 1;
            $condicionIvaDesc = 'IVA Responsable Inscripto';
        }
        
        // Verificar si hay datos de monotributo
        if (isset($personaReturn->datosMonotributo)) {
            $condicionIvaId = 6;
            $condicionIvaDesc = 'Responsable Monotributo';
        }
        
        // Verificar si hay datos de régimen general (IVA inscripto)
        if (isset($personaReturn->datosRegimenGeneral)) {
            $condicionIvaId = 1;
            $condicionIvaDesc = 'IVA Responsable Inscripto';
        }

        // Extraer domicilio fiscal
        $domicilioFiscal = null;
        if (isset($persona->domicilio) && is_array($persona->domicilio)) {
            foreach ($persona->domicilio as $dom) {
                if (isset($dom->tipoDomicilio) && $dom->tipoDomicilio === 'FISCAL') {
                    $domicilioFiscal = $dom;
                    break;
                }
            }
            // Si no hay fiscal, usar el primero
            if (!$domicilioFiscal && count($persona->domicilio) > 0) {
                $domicilioFiscal = $persona->domicilio[0];
            }
        }

        // Extraer actividades
        $actividades = [];
        if (isset($persona->idActividadPrincipal)) {
            $actividades[] = [
                'id' => $persona->idActividadPrincipal,
                'description' => $persona->descripcionActividadPrincipal ?? null,
                'principal' => true,
            ];
        }

        return [
            'cuit' => (string) ($persona->idPersona ?? ''),
            'tipo_persona' => $tipoPersona,
            'razon_social' => $persona->razonSocial ?? (($persona->apellido ?? '') . ', ' . ($persona->nombre ?? '')),
            'nombre' => $persona->nombre ?? null,
            'apellido' => $persona->apellido ?? null,
            'estado_clave' => $persona->estadoClave ?? null,
            'domicilio_fiscal' => [
                'direccion' => $domicilioFiscal->direccion ?? null,
                'localidad' => $domicilioFiscal->localidad ?? null,
                'provincia' => $domicilioFiscal->descripcionProvincia ?? null,
                'codigo_postal' => $domicilioFiscal->codigoPostal ?? null,
            ],
            'condicion_iva' => [
                'id' => $condicionIvaId,
                'description' => $condicionIvaDesc,
            ],
            'actividades' => $actividades,
            'fecha_contrato_social' => $persona->fechaContratoSocial ?? null,
            'raw' => $personaReturn,
        ];
    }

    private function log(string $level, string $message, array $context = []): void
    {
        Log::$level("[AFIP Padrón] {$message}", $context);
    }
}
