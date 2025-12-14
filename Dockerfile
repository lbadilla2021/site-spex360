# Dockerfile para Apex 360 Landing Page con PHP
FROM richarvey/nginx-php-fpm:latest

# Copiar archivos HTML al directorio de nginx
COPY *.html /var/www/html/
COPY *.php /var/www/html/

# Copiar carpeta assets
COPY assets/ /var/www/html/assets/

# Copiar carpeta blog
COPY blog/ /var/www/html/blog/

# Copiar carpeta cursos
COPY cursos/ /var/www/html/cursos/

# Copiar carpeta admin protegida
COPY admin/ /var/www/html/admin/

# Archivo de contraseñas para el área admin
COPY .htpasswd /etc/nginx/.htpasswd

# Crear carpeta cursos con permisos de escritura
RUN mkdir -p /var/www/html/cursos && \
    chown -R nginx:nginx /var/www/html/cursos && \
    chmod -R 755 /var/www/html/cursos

# Copiar configuración personalizada de nginx
COPY nginx.conf /etc/nginx/sites-available/default.conf

# Exponer puerto 80 (luego mapeado a 9500)
EXPOSE 80

# El script de inicio ya maneja nginx + php-fpm
CMD ["/start.sh"]
