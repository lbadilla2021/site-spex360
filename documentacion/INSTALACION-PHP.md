# 🐳 INSTALACIÓN DE PHP EN DOCKER - Guía Completa

## 📋 **Archivos Actualizados para PHP**

He actualizado 4 archivos para que funcione PHP:

1. ✅ **Dockerfile** - Usa imagen `richarvey/nginx-php-fpm:latest`
2. ✅ **nginx-php.conf** - Configuración Nginx con soporte PHP
3. ✅ **generate-course.php** - Script PHP generador de cursos
4. ✅ **generate-blog.php** - Script PHP generador de artículos

---

## 🚀 **INSTALACIÓN PASO A PASO**

### **Paso 1: Subir Archivos Nuevos al VPS**

```bash
# En tu computadora, subir archivos actualizados:
scp Dockerfile root@65.108.150.100:/root/docker/site-apex/
scp nginx-php.conf root@65.108.150.100:/root/docker/site-apex/
scp generate-course.php root@65.108.150.100:/root/docker/site-apex/
scp generate-blog.php root@65.108.150.100:/root/docker/site-apex/
scp admin/otec-admin.html root@65.108.150.100:/root/docker/site-apex/
scp admin/blog-admin.html root@65.108.150.100:/root/docker/site-apex/
```

### **Paso 2: Conectar al VPS**

```bash
ssh root@65.108.150.100
cd /root/docker/site-apex
```

### **Paso 3: Reemplazar nginx.conf**

```bash
# Respaldar el antiguo
cp nginx.conf nginx.conf.backup

# Usar el nuevo con soporte PHP
cp nginx-php.conf nginx.conf
```

### **Paso 4: Actualizar Dockerfile**

El nuevo Dockerfile ya usa la imagen con PHP. Verifica que diga:

```dockerfile
FROM richarvey/nginx-php-fpm:latest
```

### **Paso 5: Rebuild Docker**

```bash
# Detener contenedor actual
docker-compose down

# Limpiar imágenes antiguas (opcional)
docker system prune -a

# Construir con la nueva imagen
docker-compose build --no-cache

# Levantar contenedor
docker-compose up -d

# Ver logs para verificar
docker-compose logs -f
```

### **Paso 6: Verificar que PHP Funciona**

```bash
# Verificar que PHP está instalado
docker exec apex360-landing php -v

# Debería mostrar:
# PHP 8.x.x (cli) ...
```

---

## ✅ **Verificación del Sistema**

### **Test 1: Verificar Archivos**

```bash
docker exec apex360-landing ls -la /var/www/html/ | grep php

# Debería mostrar:
# -rw-r--r-- generate-course.php
# -rw-r--r-- generate-blog.php
```

### **Test 2: Verificar Permisos de Carpeta cursos**

```bash
docker exec apex360-landing ls -ld /var/www/html/cursos

# Debería mostrar:
# drwxr-xr-x ... nginx nginx ... cursos
```

### **Test 3: Crear Curso de Prueba**

1. Abrir: `http://65.108.150.100:9500/admin/otec-admin.html`
2. Click "+ Nuevo Curso"
3. Llenar datos:
   - Título: "Curso de Prueba PHP"
   - Duración: "8 horas"
   - Intro: "Test de generación automática"
4. Click "Guardar Curso"
5. **Debería mostrar:** "Curso creado exitosamente. Archivo HTML generado en /cursos/"

### **Test 4: Verificar Archivo Generado**

```bash
docker exec apex360-landing ls -la /var/www/html/cursos/

# Debería mostrar:
# curso-prueba-php.html
```

### **Test 5: Acceder al Curso**

Abrir en navegador:
```
http://65.108.150.100:9500/cursos/curso-prueba-php.html
```

Debería cargar la página del curso.

---

## 🔍 **Troubleshooting**

### **Problema: "File not found" al acceder a .php**

**Causa:** PHP-FPM no está procesando archivos

**Solución:**
```bash
# Verificar que PHP-FPM está corriendo
docker exec apex360-landing ps aux | grep php-fpm

# Si no aparece, revisar logs
docker-compose logs
```

### **Problema: "Permission denied" al crear archivo**

**Causa:** Carpeta cursos sin permisos de escritura

**Solución:**
```bash
docker exec apex360-landing chown -R nginx:nginx /var/www/html/cursos
docker exec apex360-landing chmod -R 755 /var/www/html/cursos
```

### **Problema: Descarga archivo en vez de crear en servidor**

**Causa:** Fetch a generate-course.php falla

**Solución:**
1. Verificar que `generate-course.php` existe:
   ```bash
   docker exec apex360-landing ls /var/www/html/generate-course.php
   ```

2. Ver logs de error PHP:
   ```bash
   docker exec apex360-landing tail -f /var/log/php-fpm/error.log
   ```

3. Probar PHP directamente:
   ```bash
   curl http://65.108.150.100:9500/generate-course.php
   ```

---

## 📊 **Comparación: Antes vs Después**

### **ANTES (Solo Nginx):**
```
Imagen: nginx:alpine (5 MB)
PHP: ❌ No disponible
Genera archivos: ❌ Solo descarga
```

### **DESPUÉS (Nginx + PHP):**
```
Imagen: richarvey/nginx-php-fpm:latest (~100 MB)
PHP: ✅ PHP 8.2
Genera archivos: ✅ Automático en /cursos/
```

---

## 🎯 **Estructura Final del Proyecto**

```
/root/docker/site-apex/
├── Dockerfile (✅ actualizado con PHP)
├── docker-compose.yml
├── nginx.conf (✅ reemplazado por nginx-php.conf)
├── generate-course.php (✅ nuevo)
├── admin/otec-admin.html (✅ actualizado)
├── otec.html
├── apex360-landing.html
├── blog.html
├── admin/blog-admin.html
├── blog/
│   └── automatizaciones-rrhh.html
└── cursos/ (✅ carpeta con permisos de escritura)
    └── (archivos generados automáticamente)
```

---

## 🔄 **Workflow Completo**

### **Crear Nuevo Curso:**

1. **Abrir Admin:**
   ```
   http://65.108.150.100:9500/admin/otec-admin.html
   ```

2. **Crear Curso:**
   - Click "+ Nuevo Curso"
   - Llenar formulario
   - Click "Guardar Curso"

3. **Sistema Automático:**
   ```
   JavaScript → POST a generate-course.php
   PHP → Genera HTML
   PHP → Guarda en /cursos/nombre-curso.html
   JavaScript → Alert "✅ Archivo generado"
   ```

4. **Verificar:**
   ```
   http://65.108.150.100:9500/cursos/nombre-curso.html
   ```

5. **Listo!** ✅

---

## ⚠️ **Notas Importantes**

### **Tamaño de Imagen:**
- La imagen `richarvey/nginx-php-fpm` es ~100 MB (vs 5 MB de nginx:alpine)
- Primera descarga será más lenta
- Pero después funciona igual de rápido

### **Persistencia de Datos:**
- Los cursos en `/cursos/` se guardan en el contenedor
- Si reconstruyes la imagen, se pierden
- **Solución:** Usar volume en docker-compose.yml:

```yaml
volumes:
  - ./cursos:/var/www/html/cursos
```

### **Actualizar Código:**
```bash
# Solo actualizar archivos (sin rebuild)
docker cp admin/otec-admin.html apex360-landing:/var/www/html/
docker cp generate-course.php apex360-landing:/var/www/html/

# Rebuild completo (si cambias Dockerfile)
docker-compose down
docker-compose build --no-cache
docker-compose up -d
```

---

## 🎉 **Resultado Final**

Una vez instalado, tendrás:

✅ **Generación automática** de archivos HTML
✅ **Sin descargas manuales**
✅ **Sin subidas por SFTP**
✅ **100% automatizado**

**Flujo:**
```
Crear curso → Click guardar → ✅ Listo (archivo en /cursos/)
```

---

## 📞 **Comandos de Referencia Rápida**

```bash
# Rebuild completo
cd /root/docker/site-apex
docker-compose down
docker-compose build --no-cache
docker-compose up -d

# Ver logs
docker-compose logs -f

# Verificar PHP
docker exec apex360-landing php -v

# Ver archivos generados
docker exec apex360-landing ls -la /var/www/html/cursos/

# Permisos cursos
docker exec apex360-landing chown -R nginx:nginx /var/www/html/cursos
docker exec apex360-landing chmod -R 755 /var/www/html/cursos
```

---

¿Listo para instalar? Sigue los pasos en orden y avísame si hay algún error.
