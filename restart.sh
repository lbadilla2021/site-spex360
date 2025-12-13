cd /root/docker/site-apex

# 1. Detener contenedor
docker-compose down

# 2. IMPORTANTE: Reconstruir SIN caché
docker-compose build --no-cache

# 3. Levantar de nuevo
docker-compose up -d

# 4. Verificar que se construyó la nueva imagen
docker images | grep apex

# 5. Verificar archivos dentro del contenedor
docker exec apex360-landing ls -lah /usr/share/nginx/html/

# 6. Ver logs
docker-compose logs -f