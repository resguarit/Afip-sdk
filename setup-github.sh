#!/bin/bash

# Script para preparar y subir el SDK a GitHub
# Uso: ./setup-github.sh

set -e

echo "🚀 Preparando repositorio para GitHub..."
echo ""

# Colores
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Verificar que Git esté instalado
if ! command -v git &> /dev/null; then
    echo -e "${RED}❌ Git no está instalado. Por favor instálalo primero.${NC}"
    exit 1
fi

# Verificar que no haya certificados
echo "🔍 Verificando que no haya certificados sensibles..."
if find . -name "*.key" -o -name "*.crt" -o -name "*.pem" | grep -v node_modules | grep -v vendor | grep -q .; then
    echo -e "${YELLOW}⚠️  ADVERTENCIA: Se encontraron archivos de certificados.${NC}"
    echo "   Por favor, verifica que estén en .gitignore"
    read -p "¿Continuar de todas formas? (y/N) " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        exit 1
    fi
fi

# Inicializar Git si no está inicializado
if [ ! -d .git ]; then
    echo "📦 Inicializando repositorio Git..."
    git init
    echo -e "${GREEN}✅ Repositorio Git inicializado${NC}"
else
    echo -e "${GREEN}✅ Git ya está inicializado${NC}"
fi

# Verificar estado
echo ""
echo "📋 Estado actual del repositorio:"
git status --short || true

# Preguntar si hacer commit inicial
echo ""
read -p "¿Hacer commit inicial? (Y/n) " -n 1 -r
echo
if [[ $REPLY =~ ^[Nn]$ ]]; then
    echo "⏭️  Saltando commit inicial"
    exit 0
fi

# Agregar todos los archivos
echo "📝 Agregando archivos..."
git add .

# Hacer commit
echo "💾 Haciendo commit inicial..."
git commit -m "Initial commit: AFIP SDK para Laravel

- Integración completa con WSAA y WSFE
- Autenticación con cache de tokens
- Autorización de comprobantes electrónicos
- Correlatividad automática
- Documentación completa"

echo -e "${GREEN}✅ Commit inicial realizado${NC}"

# Preguntar sobre GitHub
echo ""
echo "📤 ¿Quieres configurar el remoto de GitHub ahora?"
read -p "Ingresa la URL del repositorio (o presiona Enter para saltar): " REPO_URL

if [ -n "$REPO_URL" ]; then
    # Verificar si ya existe el remoto
    if git remote | grep -q origin; then
        echo "⚠️  El remoto 'origin' ya existe. ¿Reemplazarlo?"
        read -p "(y/N) " -n 1 -r
        echo
        if [[ $REPLY =~ ^[Yy]$ ]]; then
            git remote remove origin
            git remote add origin "$REPO_URL"
        fi
    else
        git remote add origin "$REPO_URL"
    fi
    
    echo -e "${GREEN}✅ Remoto configurado: $REPO_URL${NC}"
    
    # Preguntar si hacer push
    echo ""
    read -p "¿Hacer push a GitHub ahora? (Y/n) " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Nn]$ ]]; then
        # Cambiar a main si es necesario
        CURRENT_BRANCH=$(git branch --show-current 2>/dev/null || echo "main")
        if [ "$CURRENT_BRANCH" != "main" ]; then
            git branch -M main
        fi
        
        echo "🚀 Haciendo push a GitHub..."
        git push -u origin main
        
        echo -e "${GREEN}✅ ¡Repositorio subido a GitHub exitosamente!${NC}"
    fi
else
    echo "⏭️  Saltando configuración de remoto"
    echo ""
    echo "Para configurar manualmente:"
    echo "  git remote add origin https://github.com/USERNAME/afip-sdk-resguar.git"
    echo "  git push -u origin main"
fi

echo ""
echo -e "${GREEN}🎉 ¡Listo! Tu repositorio está preparado.${NC}"
echo ""
echo "Próximos pasos:"
echo "1. Revisa INSTRUCCIONES_GITHUB.md para más detalles"
echo "2. Configura el repositorio en GitHub (descripción, topics, etc.)"
echo "3. Considera agregar GitHub Actions para CI/CD"

