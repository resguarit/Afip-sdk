# Política de Seguridad

## Versiones Soportadas

Actualmente soportamos las siguientes versiones con actualizaciones de seguridad:

| Versión | Soportada          |
| ------- | ------------------ |
| 1.x.x   | :white_check_mark: |

## Reportar una Vulnerabilidad

Si descubres una vulnerabilidad de seguridad, por favor:

1. **NO** crees un issue público
2. Envía un email a: **security@resguar.com**
3. Incluye:
   - Descripción detallada de la vulnerabilidad
   - Pasos para reproducir
   - Impacto potencial
   - Sugerencias de fix (si las tienes)

## Proceso

1. Recibirás una confirmación en 48 horas
2. Evaluaremos la vulnerabilidad
3. Te mantendremos informado del progreso
4. Publicaremos un fix y te daremos crédito (si lo deseas)

## Buenas Prácticas

### ⚠️ NUNCA subas al repositorio:
- Certificados digitales (`.key`, `.crt`, `.pem`)
- Claves privadas
- Contraseñas
- CUITs reales
- Tokens de autenticación

### ✅ Siempre:
- Usa variables de entorno para datos sensibles
- Verifica que `.gitignore` esté configurado correctamente
- Revisa los archivos antes de hacer commit
- Usa certificados de testing para desarrollo

## Agradecimientos

Agradecemos a todos los que reportan vulnerabilidades de manera responsable.

