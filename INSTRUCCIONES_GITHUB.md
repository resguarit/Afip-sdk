# Instrucciones para Subir a GitHub

## Paso 1: Preparar el Repositorio Local

### 1.1 Inicializar Git (si no está inicializado)

```bash
git init
```

### 1.2 Verificar que .gitignore esté correcto

El archivo `.gitignore` ya está configurado para excluir:
- Certificados (`.key`, `.crt`, `.pem`)
- PDFs de documentación
- Archivos de entorno (`.env`)
- Vendor y archivos temporales

### 1.3 Verificar archivos sensibles

**IMPORTANTE**: Antes de hacer commit, verifica que NO haya:
- Certificados digitales
- Claves privadas
- Contraseñas en código
- CUITs reales en ejemplos

```bash
# Verificar que no haya certificados
find . -name "*.key" -o -name "*.crt" -o -name "*.pem" | grep -v node_modules

# Verificar que no haya información sensible en código
grep -r "tu_password\|tu_cuit\|20123456789" --include="*.php" --include="*.md" src/ tests/
```

## Paso 2: Hacer el Primer Commit

```bash
# Agregar todos los archivos
git add .

# Verificar qué se va a subir (importante!)
git status

# Hacer commit inicial
git commit -m "Initial commit: AFIP SDK para Laravel

- Integración completa con WSAA y WSFE
- Autenticación con cache de tokens
- Autorización de comprobantes electrónicos
- Correlatividad automática
- Documentación completa"
```

## Paso 3: Crear Repositorio en GitHub

### Opción A: Desde la Web de GitHub

1. Ve a [GitHub](https://github.com)
2. Click en "New repository"
3. Nombre: `afip-sdk-resguar` (o el que prefieras)
4. Descripción: "SDK independiente y reutilizable para integración con AFIP - Facturación Electrónica"
5. **NO** inicialices con README, .gitignore o LICENSE (ya los tenemos)
6. Elige si será público o privado
7. Click en "Create repository"

### Opción B: Desde la CLI de GitHub

```bash
# Si tienes GitHub CLI instalado
gh repo create afip-sdk-resguar --public --description "SDK independiente y reutilizable para integración con AFIP - Facturación Electrónica"
```

## Paso 4: Conectar y Subir

```bash
# Agregar el remoto (reemplaza USERNAME con tu usuario de GitHub)
git remote add origin https://github.com/USERNAME/afip-sdk-resguar.git

# O si prefieres SSH
git remote add origin git@github.com:USERNAME/afip-sdk-resguar.git

# Verificar el remoto
git remote -v

# Cambiar a rama main (si estás en otra)
git branch -M main

# Subir el código
git push -u origin main
```

## Paso 5: Configurar el Repositorio en GitHub

### 5.1 Agregar Descripción y Topics

En la página del repositorio:
1. Click en el engranaje ⚙️ al lado de "About"
2. Agrega descripción: "SDK independiente y reutilizable para integración con AFIP - Facturación Electrónica"
3. Agrega topics: `afip`, `facturacion-electronica`, `argentina`, `laravel`, `sdk`, `php`

### 5.2 Configurar README como Página Principal

El README.md ya está configurado y se mostrará automáticamente.

### 5.3 Agregar Badges (Opcional)

Puedes agregar badges al README. Ya están incluidos algunos básicos.

### 5.4 Configurar GitHub Pages (Opcional)

Si quieres documentación en GitHub Pages:
1. Settings → Pages
2. Source: Deploy from a branch
3. Branch: `main` / `docs`

## Paso 6: Configuraciones Adicionales

### 6.1 Proteger la Rama Main

1. Settings → Branches
2. Add branch protection rule
3. Branch name pattern: `main`
4. Marcar:
   - Require pull request reviews
   - Require status checks to pass
   - Require branches to be up to date

### 6.2 Configurar GitHub Actions (Opcional)

Puedes crear `.github/workflows/ci.yml` para CI/CD:

```yaml
name: CI

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.1'
      - name: Install dependencies
        run: composer install
      - name: Run tests
        run: composer test
```

### 6.3 Configurar Releases

Para crear releases:
1. Ve a Releases
2. Click en "Create a new release"
3. Tag: `v1.0.0`
4. Title: `v1.0.0 - Initial Release`
5. Descripción: Copia del CHANGELOG.md
6. Publicar release

## Paso 7: Publicar en Packagist (Opcional)

Si quieres que el paquete sea instalable vía Composer:

1. Crea cuenta en [Packagist](https://packagist.org)
2. Submit package
3. URL del repositorio: `https://github.com/USERNAME/afip-sdk-resguar`
4. Packagist detectará automáticamente el `composer.json`

## Checklist Final

Antes de hacer público, verifica:

- [ ] No hay certificados en el repositorio
- [ ] No hay contraseñas en el código
- [ ] No hay CUITs reales en ejemplos
- [ ] `.gitignore` está configurado correctamente
- [ ] README.md está completo y actualizado
- [ ] LICENSE está presente
- [ ] CHANGELOG.md está actualizado
- [ ] Todos los archivos de documentación están presentes
- [ ] El código sigue las convenciones
- [ ] Los tests pasan

## Comandos Útiles

```bash
# Ver qué archivos se subirán
git status

# Ver diferencias
git diff

# Ver historial
git log --oneline

# Crear una rama para desarrollo
git checkout -b develop

# Sincronizar con GitHub
git fetch origin
git pull origin main
```

## Siguiente Paso: Desarrollo Continuo

```bash
# Para futuros cambios
git checkout -b feature/nueva-funcionalidad
# ... hacer cambios ...
git add .
git commit -m "Descripción del cambio"
git push origin feature/nueva-funcionalidad
# Crear Pull Request en GitHub
```

## Soporte

Si tienes problemas:
- Revisa la [Guía de Pruebas](GUIA_PRUEBAS.md)
- Abre un [Issue](https://github.com/USERNAME/afip-sdk-resguar/issues)
- Consulta la [Documentación](README.md)

¡Listo! Tu SDK está en GitHub 🚀

