#!/usr/bin/env bash
# Deploy/atualização do portal em produção.
# Uso: cd itsm-portal && bash deploy.sh
set -e
cd "$(dirname "$0")"
COMPOSE="docker compose -f docker-compose.prod.yml"

if [ ! -f .env ]; then
    echo "ERRO: arquivo .env não existe."
    echo "   Faça: cp .env.prod.example .env  &&  nano .env  (preencha as senhas)"
    exit 1
fi

echo ">> Baixando atualizações do Git..."
git pull --ff-only

# Descriptografa a chave da Google Agenda (se ainda não existir)
if [ -f storage/app/google-service-account.enc ] && [ ! -f storage/app/google-service-account.json ]; then
    echo ">> Configurando chave da Google Agenda..."
    openssl enc -d -aes-256-cbc -pbkdf2 -in storage/app/google-service-account.enc -out storage/app/google-service-account.json -k "fourline2026"
    chmod 644 storage/app/google-service-account.json 2>/dev/null || true
fi

# Garante a ativação e o ID da Google Agenda no .env
if [ -f storage/app/google-service-account.json ]; then
    sed -i 's/^GOOGLE_CALENDAR_SYNC=.*/GOOGLE_CALENDAR_SYNC=true/' .env 2>/dev/null || true
    sed -i 's/^GOOGLE_CALENDAR_ID=.*/GOOGLE_CALENDAR_ID=fourline.servicos@gmail.com/' .env 2>/dev/null || true
    if ! grep -q "^GOOGLE_CALENDAR_SYNC=" .env; then
        echo "GOOGLE_CALENDAR_SYNC=true" >> .env
    fi
    if ! grep -q "^GOOGLE_CALENDAR_ID=" .env; then
        echo "GOOGLE_CALENDAR_ID=fourline.servicos@gmail.com" >> .env
    fi
fi

echo ">> Subindo containers (build se necessário)..."
$COMPOSE up -d --build

echo ">> Instalando dependências..."
$COMPOSE exec -T app composer install --no-dev --optimize-autoloader --no-interaction

# Gera a APP_KEY na primeira vez (se estiver vazia).
if ! grep -q "^APP_KEY=base64" .env; then
    echo ">> Gerando APP_KEY..."
    $COMPOSE exec -T app php artisan key:generate --force
fi

echo ">> Rodando migrações..."
$COMPOSE exec -T app php artisan migrate --force

echo ">> Atualizando caches..."
$COMPOSE exec -T app php artisan config:cache
$COMPOSE exec -T app php artisan route:cache
$COMPOSE exec -T app php artisan view:cache

echo ">> Testando sincronização da Google Agenda..."
$COMPOSE exec -T app php artisan agenda:google-sync || true

echo ""
echo ">> Deploy concluído. Acesse:  http://<IP-da-VM>:8083"
