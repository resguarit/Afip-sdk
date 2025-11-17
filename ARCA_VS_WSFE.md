# ARCA vs WSFE: ¿Cuál Usar para Consultar Facturas?

## ❓ Tu Pregunta

> "¿No tendría que usar un Web Service de ARCA para ver cuál fue la última factura hecha?"

## ✅ Respuesta Directa

**No. ARCA no tiene un Web Service para consultar facturas. El Web Service correcto es WSFE.**

## 🔍 Explicación Detallada

### ¿Qué es ARCA?

**ARCA** (Administración de Relaciones con Contribuyentes de AFIP) es:
- ✅ **Portal web administrativo** de AFIP
- ✅ Donde gestionas certificados digitales
- ✅ Donde configuras puntos de venta
- ✅ Donde administras datos del contribuyente
- ❌ **NO tiene Web Service** para consultar facturas
- ❌ **NO almacena** facturas autorizadas

### ¿Qué es WSFE?

**WSFE** (Web Service de Facturación Electrónica) es:
- ✅ **Web Service** de AFIP (no es un portal web)
- ✅ Donde se **autorizan** las facturas
- ✅ Donde se **almacenan** las facturas autorizadas
- ✅ Tiene el método `FECompUltimoAutorizado` para consultar última factura
- ✅ Tiene el método `FECAESolicitar` para autorizar facturas

## 📊 Comparación

| Aspecto | ARCA | WSFE |
|---------|------|------|
| **Tipo** | Portal web | Web Service (SOAP) |
| **Acceso** | Navegador web | API/SOAP |
| **Función** | Gestión administrativa | Facturación electrónica |
| **Certificados** | ✅ Gestiona | ❌ No |
| **Puntos de venta** | ✅ Configura | ❌ No |
| **Autorizar facturas** | ❌ No | ✅ Sí |
| **Consultar última factura** | ❌ No tiene WS | ✅ Sí (FECompUltimoAutorizado) |
| **Almacenar facturas** | ❌ No | ✅ Sí |

## ✅ ¿Qué Usa el SDK?

El SDK usa **WSFE** (Web Service de AFIP) para:

1. **Consultar última factura**: `FECompUltimoAutorizado`
2. **Autorizar facturas**: `FECAESolicitar`
3. **Obtener tipos de comprobantes**: `FEParamGetTiposCbte` (pendiente)
4. **Obtener puntos de venta**: `FEParamGetPtosVenta` (pendiente)

### Código del SDK

```php
// En WsfeService.php
public function getLastAuthorizedInvoice(...): array
{
    // Crea cliente SOAP para WSFE (no ARCA)
    $client = SoapHelper::createClient($this->url); // URL de WSFE
    
    // Llama método de WSFE (no de ARCA)
    $soapResponse = SoapHelper::call(
        $client,
        'FECompUltimoAutorizado',  // ← Método de WSFE
        $params
    );
    
    return $this->parseLastInvoiceResponse($soapResponse);
}
```

## 🔗 URLs de los Servicios

### WSFE (Web Service de Facturación Electrónica)

**Testing (Homologación):**
```
https://wswhomo.afip.gov.ar/wsfev1/service.asmx?WSDL
```

**Producción:**
```
https://servicios1.afip.gov.ar/wsfev1/service.asmx?WSDL
```

### ARCA (Portal Web)

**Testing:**
```
https://www.afip.gob.ar/arqa/
```

**Producción:**
```
https://www.afip.gob.ar/arqa/
```

**Nota:** ARCA es un portal web, no tiene WSDL ni Web Service.

## 📝 Métodos Disponibles en WSFE

El Web Service WSFE tiene estos métodos principales:

| Método | Función | Estado en SDK |
|--------|---------|---------------|
| `FECompUltimoAutorizado` | Consulta último comprobante | ✅ Implementado |
| `FECAESolicitar` | Autoriza comprobante | ✅ Implementado |
| `FEParamGetTiposCbte` | Obtiene tipos de comprobantes | ⏳ Pendiente |
| `FEParamGetPtosVenta` | Obtiene puntos de venta | ⏳ Pendiente |
| `FEParamGetTiposDoc` | Obtiene tipos de documento | ⏳ Pendiente |
| `FEParamGetTiposConcepto` | Obtiene tipos de concepto | ⏳ Pendiente |
| `FEParamGetTiposIva` | Obtiene tipos de IVA | ⏳ Pendiente |

## ✅ Conclusión

**El SDK está usando el Web Service correcto:**

- ✅ **WSFE** es el Web Service de AFIP para facturación
- ✅ `FECompUltimoAutorizado` es el método correcto de WSFE
- ❌ ARCA no tiene Web Service para consultar facturas
- ❌ ARCA es solo un portal web administrativo

**No necesitas cambiar nada.** El SDK ya está usando el Web Service correcto (WSFE).

## 📚 Referencias

- [Documentación oficial de WSFE](https://www.afip.gob.ar/fe/documentos/)
- [Manual del Desarrollador AFIP](https://www.afip.gob.ar/fe/documentos/manual_desarrollador_COMPG_v2_10.pdf)

---

**¿Tienes más dudas?** El SDK está implementado correctamente usando WSFE, que es el Web Service oficial de AFIP para facturación electrónica.

