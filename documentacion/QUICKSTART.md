# 🚀 INICIO RÁPIDO - Apex 360

## Opción 1: Despliegue Automático (Más Fácil)

### Desde tu computadora local:

```bash
# 1. Dale permisos al script de subida
chmod +x upload-to-vps.sh

# 2. Sube todo al VPS (reemplaza con tu IP y usuario)
./upload-to-vps.sh root@TU_IP_VPS

# 3. Conecta al VPS
ssh root@TU_IP_VPS

# 4. Ve al directorio
cd /opt/apex360

# 5. Ejecuta el despliegue automático
./deploy.sh
```

**¡Listo!** Tu sitio estará en `http://TU_IP:9500`

---

## Opción 2: Despliegue Manual

### En tu VPS:

```bash
# 1. Crear directorio
mkdir -p /opt/apex360
cd /opt/apex360

# 2. Subir archivos (usa SFTP/SCP/WinSCP)
# Archivos necesarios:
# - apex360-landing.html
# - otec.html  
# - curso-detalle.html
# - otec-admin.html
# - Dockerfile
# - docker-compose.yml
# - nginx.conf

# 3. Construir y ejecutar
docker-compose up -d --build

# 4. Verificar
docker-compose ps
curl http://localhost:9500
```

---

## URLs del Sitio

Una vez desplegado, accede a:

- **Landing Principal:** `http://TU_IP:9500/apex360-landing.html`
- **OTEC (Cursos):** `http://TU_IP:9500/otec.html`
- **Panel Admin:** `http://TU_IP:9500/otec-admin.html`

---

## Comandos Útiles

```bash
# Ver logs
docker-compose logs -f

# Reiniciar
docker-compose restart

# Detener
docker-compose down

# Actualizar después de cambios
docker-compose up -d --build

# Ver estado
docker-compose ps
```

---

## Configurar Dominio

### 1. Apuntar DNS
```
A Record: apex360.cl → TU_IP_VPS
A Record: www.apex360.cl → TU_IP_VPS
```

### 2. Nginx Reverse Proxy
```bash
# Crear configuración
sudo nano /etc/nginx/sites-available/apex360.conf
```

Contenido:
```nginx
server {
    listen 80;
    server_name apex360.cl www.apex360.cl;
    
    location / {
        proxy_pass http://localhost:9500;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }
}
```

```bash
# Activar
sudo ln -s /etc/nginx/sites-available/apex360.conf /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### 3. SSL Gratis
```bash
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d apex360.cl -d www.apex360.cl
```

---

## Troubleshooting

### Puerto 9500 ocupado?
```bash
# Ver qué lo usa
sudo lsof -i :9500

# Cambiar puerto en docker-compose.yml
# Editar línea: "9500:80" → "8080:80"
```

### Contenedor no inicia?
```bash
# Ver logs
docker logs apex360-landing

# Reconstruir desde cero
docker-compose down
docker system prune -a
docker-compose up -d --build
```

### Firewall bloqueando?
```bash
sudo ufw allow 9500/tcp
sudo ufw reload
```

---

## Checklist Instalación

- [ ] Docker instalado en VPS
- [ ] Docker Compose instalado
- [ ] Archivos subidos a `/opt/apex360`
- [ ] `./deploy.sh` ejecutado
- [ ] Sitio accesible en `http://IP:9500`
- [ ] DNS configurado (si aplica)
- [ ] Nginx reverse proxy (si aplica)
- [ ] SSL instalado (si aplica)

---

## Soporte

**Documentación completa:** Ver `README.md`

**Estructura:**
```
/opt/apex360/
├── apex360-landing.html
├── otec.html
├── curso-detalle.html
├── otec-admin.html
├── Dockerfile
├── docker-compose.yml
├── nginx.conf
└── deploy.sh
```

---

**¡Éxito con tu despliegue! 🎉**
