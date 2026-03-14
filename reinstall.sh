#!/bin/bash
cd ~/stm-taller-motos

echo "🔄 Reinstalando STM Taller de Motos..."

# Detener y eliminar contenedores
docker-compose down

# Eliminar volúmenes (opcional, elimina datos)
# docker-compose down -v

# Reconstruir imágenes
docker-compose build --no-cache

# Iniciar contenedores
docker-compose up -d

echo "⏳ Esperando que los servicios inicien..."
sleep 10

# Verificar estado
if docker ps | grep -q "stm_nginx" && docker ps | grep -q "stm_php"; then
    echo "✅ Reinstalación completada"
    
    # Verificar archivos
    echo "📁 Verificando archivos en el contenedor..."
    docker exec stm_nginx ls -la /var/www/html/
    
    IP=$(hostname -I | awk '{print $1}')
    echo "🌐 Accede a: http://$IP/check.php"
else
    echo "❌ Error en la reinstalación"
    docker-compose logs
fi
