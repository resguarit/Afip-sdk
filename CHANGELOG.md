# Changelog

Todos los cambios notables en este proyecto serán documentados en este archivo.

El formato está basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.0.0/),
y este proyecto adhiere a [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Integración completa con Web Services de AFIP (WSAA, WSFE)
- Autenticación automática con cache de tokens (12 horas)
- Autorización de comprobantes electrónicos con obtención de CAE
- Consulta automática del último comprobante para garantizar correlatividad
- Builder Pattern para construcción flexible de comprobantes
- Soporte para múltiples fuentes de datos (Eloquent, arrays, objetos)
- Helpers para generación de TRA, CMS (PKCS#7), y mapeo de datos
- Validación de datos con reglas de negocio
- DTOs (Data Transfer Objects) para respuestas estructuradas
- Manejo robusto de errores con excepciones personalizadas
- Logging integrado con niveles configurables
- Retry logic con exponential backoff para errores temporales
- Soporte para entornos de testing y producción
- Gestión de certificados digitales
- Documentación completa (README, Guía de Pruebas, Mejores Prácticas)

### Security
- Protección de certificados digitales (nunca se suben al repositorio)
- Validación de datos antes de enviar a AFIP

## [1.0.0] - 2024-01-XX

### Added
- Versión inicial del SDK
- Integración con WSAA (Web Service de Autenticación y Autorización)
- Integración con WSFE (Web Service de Facturación Electrónica)
- Cache automático de tokens
- Correlatividad automática de números de comprobante
- Documentación completa

