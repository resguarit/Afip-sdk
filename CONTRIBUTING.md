# Guía de Contribución

¡Gracias por considerar contribuir al SDK de AFIP!

## Cómo Contribuir

### Reportar Bugs

Si encuentras un bug, por favor:
1. Verifica que no esté reportado ya en los [Issues](https://github.com/resguar/afip-sdk-resguar/issues)
2. Crea un nuevo issue usando la plantilla de [Bug Report](.github/ISSUE_TEMPLATE/bug_report.md)
3. Incluye toda la información relevante (versiones, logs, pasos para reproducir)

### Sugerir Funcionalidades

Si tienes una idea para una nueva funcionalidad:
1. Verifica que no esté solicitada ya en los [Issues](https://github.com/resguar/afip-sdk-resguar/issues)
2. Crea un nuevo issue usando la plantilla de [Feature Request](.github/ISSUE_TEMPLATE/feature_request.md)
3. Describe claramente la funcionalidad y su caso de uso

### Contribuir con Código

1. **Fork** el repositorio
2. **Crea una rama** para tu feature (`git checkout -b feature/AmazingFeature`)
3. **Haz commit** de tus cambios (`git commit -m 'Add some AmazingFeature'`)
4. **Push** a la rama (`git push origin feature/AmazingFeature`)
5. **Abre un Pull Request**

## Estándares de Código

- Seguir **PSR-12** para estilo de código
- Usar **type hints** estrictos (PHP 8.1+)
- Agregar **DocBlocks** completos
- Escribir **tests** para nuevas funcionalidades
- Mantener la **cobertura de tests** alta

## Estructura del Proyecto

```
src/
├── Builders/          # Builders para construcción de datos
├── Contracts/         # Interfaces/Contratos
├── DTOs/              # Data Transfer Objects
├── Exceptions/        # Excepciones personalizadas
├── Facades/           # Facades de Laravel
├── Helpers/           # Helpers y utilidades
├── Models/            # Modelos Eloquent
└── Services/          # Servicios principales
```

## Testing

Antes de hacer commit:
```bash
composer test
```

Para coverage:
```bash
composer test-coverage
```

## Documentación

- Actualiza el README si es necesario
- Agrega ejemplos de uso
- Documenta cambios en CHANGELOG.md

## Preguntas?

Si tienes preguntas, abre un issue o contacta a los mantenedores.

¡Gracias por contribuir! 🎉

