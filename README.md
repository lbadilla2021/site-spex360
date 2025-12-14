# Apex 360 - Landing Page & OTEC System

Sistema completo de landing page para consultoría RRHH + sistema OTEC con gestión de cursos.

## 🚀 Despliegue Rápido con Docker

### Prerequisitos
- Docker instalado en tu VPS
- Docker Compose instalado
- Puertos disponibles: 9500

### Instalación en VPS

#### Opción 1: Despliegue Rápido (Recomendado)

```bash
# 1. Conectar a tu VPS
ssh usuario@tu-vps-ip

# 2. Crear directorio del proyecto
mkdir -p /opt/apex360
cd /opt/apex360

# 3. Subir todos los archivos al servidor
# Usa SCP, SFTP, o Git para subir:
# - apex360-landing.html
# - otec.html
# - curso-detalle.html
# - admin/otec-admin.html
# - Dockerfile
# - docker-compose.yml
# - nginx.conf

# 4. Construir y levantar el contenedor
docker-compose up -d --build

# 5. Verificar que está corriendo
docker-compose ps
docker-compose logs -f

# 6. Probar
curl http://localhost:9500
```

**Tu sitio estará disponible en:** `http://tu-vps-ip:9500`

---

#### Opción 2: Despliegue Manual con Docker

```bash
# 1. Construir imagen
docker build -t apex360-landing .

# 2. Ejecutar contenedor
docker run -d \
  --name apex360-landing \
  -p 9500:80 \
  --restart unless-stopped \
  apex360-landing

# 3. Verificar
docker ps
docker logs apex360-landing
```

---

### 📋 Comandos Útiles

```bash
# Ver logs
docker-compose logs -f

# Reiniciar contenedor
docker-compose restart

# Detener contenedor
docker-compose down

# Reconstruir después de cambios
docker-compose up -d --build

# Ver estado
docker-compose ps

# Acceder al contenedor
docker exec -it apex360-landing sh

# Ver archivos dentro del contenedor
docker exec apex360-landing ls -la /usr/share/nginx/html/
```

---

### 📧 Configurar envío de correos

El contenedor **no** incluye un servidor de correo local y el formulario de contacto exige SMTP externo. Si `SMTP_HOST` no está definido la API de contacto responderá con error y no intentará usar sendmail local. Configura las variables de entorno (en `.env` o exportadas antes de ejecutar `docker-compose`) para habilitar el envío.

Variables disponibles:

- `SMTP_HOST`: Host del servidor SMTP (requerido para habilitar SMTP).
- `SMTP_PORT`: Puerto del servidor (por defecto `587`).
- `SMTP_USER`: Usuario de autenticación (opcional si el servidor no requiere).
- `SMTP_PASS`: Contraseña del usuario.
- `SMTP_SECURE`: `tls`, `ssl` o `none` (por defecto `tls`).
- `SMTP_FROM`: Correo que aparecerá como remitente (por defecto `contacto@apex360.cl`).

Ejemplo de archivo `.env`:

```env
SMTP_HOST=smtp.tuproveedor.com
SMTP_PORT=587
SMTP_USER=usuario@tudominio.cl
SMTP_PASS=contraseña-segura
SMTP_SECURE=tls
SMTP_FROM=contacto@apex360.cl
```

---

### 🔧 Actualizar Contenido

#### Método 1: Reconstruir (Cambios permanentes)

```bash
# 1. Editar archivos HTML en tu servidor
nano apex360-landing.html

# 2. Reconstruir y relanzar
docker-compose down
docker-compose up -d --build
```

#### Método 2: Hot Reload (Desarrollo)

Descomenta las líneas de volúmenes en `docker-compose.yml`:

```yaml
volumes:
  - ./apex360-landing.html:/usr/share/nginx/html/apex360-landing.html:ro
  - ./otec.html:/usr/share/nginx/html/otec.html:ro
  - ./curso-detalle.html:/usr/share/nginx/html/curso-detalle.html:ro
  - ./admin/otec-admin.html:/usr/share/nginx/html/admin/otec-admin.html:ro
```

Luego:
```bash
docker-compose up -d
```

Ahora puedes editar archivos y verás cambios inmediatos.

---https://github.com/lbadilla2021/site-spex360/pull/16/conflict?name=send-contact.php&ancestor_oid=8ac177fdc7b6f5e6972b5c55841f90e720bc223b&base_oid=9208a9996564712f4ae153be3f6cf52d5ee946d2&head_oid=8645cb46879e0affe796376d8ae7d87d22794c35

### 🌐 Configurar Dominio

#### Con Nginx Reverse Proxy en el Host

```nginx
# /etc/nginx/sites-available/apex360.conf

server {
    listen 80;
    server_name apex360.cl www.apex360.cl;
    
    location / {
        proxy_pass http://localhost:9500;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

```bash
# Activar configuración
sudo ln -s /etc/nginx/sites-available/apex360.conf /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

#### SSL con Certbot

```bash
# Instalar certbot
sudo apt update
sudo apt install certbot python3-certbot-nginx

# Obtener certificado
sudo certbot --nginx -d apex360.cl -d www.apex360.cl

# Renovación automática ya está configurada
sudo certbot renew --dry-run
```

---

### 📊 Monitoreo

#### Ver uso de recursos
```bash
docker stats apex360-landing
```

#### Ver logs en tiempo real
```bash
docker-compose logs -f --tail=100
```

#### Verificar salud del contenedor
```bash
docker inspect apex360-landing | grep Status
```

---

### 🔒 Seguridad

#### 1. Firewall
```bash
# Permitir solo puerto necesario
sudo ufw allow 9500/tcp
sudo ufw enable
```

#### 2. Fail2ban (opcional)
```bash
sudo apt install fail2ban
sudo systemctl enable fail2ban
```

#### 3. Actualizar regularmente
```bash
# Actualizar imagen base
docker pull nginx:alpine
docker-compose up -d --build
```

---

### 🗂️ Estructura de Archivos

```
/opt/apex360/
├── apex360-landing.html    # Landing principal
├── otec.html               # Landing OTEC
├── curso-detalle.html      # Detalle de curso
├── admin/otec-admin.html         # Panel administración
├── Dockerfile              # Configuración Docker
├── docker-compose.yml      # Orquestación
├── nginx.conf              # Config Nginx
└── README.md               # Esta guía
```

---

### 🐛 Troubleshooting

#### Problema: Puerto 9500 ocupado
```bash
# Ver qué usa el puerto
sudo lsof -i :9500
sudo netstat -tulpn | grep 9500

# Cambiar puerto en docker-compose.yml
# Modificar: "9500:80" → "OTRO_PUERTO:80"
```

#### Problema: Contenedor no inicia
```bash
# Ver logs completos
docker logs apex360-landing

# Verificar sintaxis nginx
docker exec apex360-landing nginx -t
```

#### Problema: Cambios no se ven
```bash
# Limpiar caché del navegador
# O forzar reconstrucción
docker-compose down
docker system prune -a
docker-compose up -d --build
```

#### Problema: LocalStorage no persiste
```
LocalStorage es por navegador del cliente.
Para persistencia real en producción, considera:
- Backend PHP + MySQL
- WordPress + Custom Post Type  
- Firebase / Supabase
- Headless CMS (Strapi, Directus)
```

---

### 📦 Backup

#### Backup manual
```bash
# Backup archivos
tar -czf apex360-backup-$(date +%Y%m%d).tar.gz /opt/apex360/

# Restaurar
tar -xzf apex360-backup-YYYYMMDD.tar.gz -C /
```

#### Backup automático (cron)
```bash
# Editar crontab
crontab -e

# Agregar (backup diario 2am)
0 2 * * * tar -czf /backups/apex360-$(date +\%Y\%m\%d).tar.gz /opt/apex360/
```

---

### 🔄 CI/CD con GitHub (Opcional)

#### 1. Crear GitHub Repo
```bash
cd /opt/apex360
git init
git add .
git commit -m "Initial commit"
git remote add origin git@github.com:usuario/apex360.git
git push -u origin main
```

#### 2. Auto-deploy con webhook
```bash
# Instalar webhook
sudo apt install webhook

# Crear script de deploy
cat > /opt/apex360/deploy.sh << 'EOF'
#!/bin/bash
cd /opt/apex360
git pull origin main
docker-compose up -d --build
EOF

chmod +x /opt/apex360/deploy.sh
```

---

### 📈 Optimización

#### Habilitar caché agresivo
Ya está configurado en `nginx.conf` para assets estáticos.

#### Comprimir respuestas
Gzip ya habilitado en `nginx.conf`.

#### HTTP/2
```bash
# Si usas SSL, HTTP/2 se activa automáticamente
# Verifica en nginx del host
```

---

### 📞 Soporte

**Archivos incluidos:**
- ✅ 4 páginas HTML completas
- ✅ Sistema OTEC autoadministrable
- ✅ Configuración Docker lista
- ✅ Nginx optimizado
- ✅ Docker Compose configurado

**URLs del sitio:**
- Landing principal: `http://tu-ip:9500/apex360-landing.html`
- OTEC: `http://tu-ip:9500/otec.html`
- Admin: `http://tu-ip:9500/admin/otec-admin.html` (protegido con usuario `lbadilla1970` y clave `CerroColorado.2020`)

> ⚠️ Si prefieres otras credenciales, edita `.htpasswd` antes de desplegar (usa `openssl passwd -apr1 "tu-clave"` para generar el hash) y reemplaza el usuario según necesites.

---

## 🎯 Próximos Pasos Recomendados

1. ✅ Desplegar en VPS
2. ✅ Configurar dominio apex360.cl
3. ✅ Instalar SSL con Certbot
4. ⚠️ Migrar LocalStorage a backend real (para producción)
5. ⚠️ Implementar analytics (Google Analytics 4)
6. ⚠️ Configurar formulario de contacto funcional
7. ⚠️ Backup automático diario

---

## 📝 Licencia

© 2025 Apex 360 - Luciano Badilla
Todos los derechos reservados.
