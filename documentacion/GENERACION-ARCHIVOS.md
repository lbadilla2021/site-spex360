# 📂 GENERACIÓN DE ARCHIVOS DE CURSOS - Dos Opciones

## ⚠️ **Limitación de JavaScript en Navegador**

JavaScript en el navegador **NO puede escribir archivos directamente** en el disco por seguridad. Por eso te doy **dos opciones**:

---

## ✅ **OPCIÓN 1: Con PHP (Automático) - RECOMENDADO**

### **Cómo Funciona:**

1. **Crear curso** en `otec-admin.html`
2. Click "Guardar Curso"
3. JavaScript envía datos a `generate-course.php`
4. **PHP genera el archivo** directamente en `/cursos/`
5. ✅ **Listo** - El archivo ya está en el servidor

### **Archivos Necesarios:**

- ✅ `otec-admin.html` (actualizado)
- ✅ `generate-course.php` (nuevo)

### **Instalación en VPS:**

```bash
cd /root/docker/site-apex

# 1. Subir archivos
# - otec-admin.html (actualizado)
# - generate-course.php (nuevo)

# 2. Dar permisos de escritura a carpeta cursos
mkdir -p cursos
chmod 755 cursos

# 3. El Dockerfile ya está configurado
# No necesitas cambiar nada más

# 4. Rebuild Docker
docker-compose down
docker-compose build --no-cache
docker-compose up -d
```

### **Uso:**

```
1. Abrir: http://tu-ip:9500/otec-admin.html
2. Click "+ Nuevo Curso"
3. Llenar datos
4. Click "Guardar Curso"
5. ✅ Archivo generado en /cursos/ automáticamente
```

### **Ventajas:**
✅ Totalmente automático
✅ No necesitas descargar/subir archivos
✅ Más rápido
✅ Menos pasos

### **Requisitos:**
⚠️ Necesitas PHP en el servidor (Docker ya lo tiene con nginx-php o puedes agregar PHP-FPM)

---

## 📥 **OPCIÓN 2: Descarga Manual (Fallback)**

Si PHP no está disponible, el sistema hace **fallback automático** a descarga:

### **Cómo Funciona:**

1. **Crear curso** en `otec-admin.html`
2. Click "Guardar Curso"
3. JavaScript intenta llamar a PHP
4. Si falla → **Descarga automática** del HTML
5. **Tú subes manualmente** el archivo a `/cursos/`

### **Pasos:**

```bash
# 1. El navegador descarga: curso-nombre.html

# 2. En tu VPS, subir archivo
scp curso-nombre.html root@IP:/root/docker/site-apex/cursos/

# 3. Rebuild Docker (solo si es necesario)
cd /root/docker/site-apex
docker-compose up -d --build
```

### **Ventajas:**
✅ Funciona sin PHP
✅ Funciona sin servidor (prueba local)
✅ Siempre disponible como backup

### **Desventajas:**
❌ Pasos manuales extra
❌ Más lento
❌ Requiere acceso SFTP/SCP

---

## 🐳 **Para Usar PHP en Docker**

### **Opción A: Agregar PHP a Nginx**

Necesitas cambiar la imagen base de nginx a nginx-php:

```dockerfile
# En Dockerfile, cambiar:
FROM nginx:alpine

# Por:
FROM php:8.2-fpm-alpine
```

Pero esto complica la configuración. **Mejor es Opción B...**

### **Opción B: Nginx + PHP-FPM (Recomendado)**

Crear nuevo `docker-compose.yml`:

```yaml
version: '3.8'

services:
  apex360-web:
    build: .
    container_name: apex360-landing
    ports:
      - "9500:80"
    restart: unless-stopped
    environment:
      - TZ=America/Santiago
    volumes:
      - ./cursos:/usr/share/nginx/html/cursos
    networks:
      - apex-network

  apex360-php:
    image: php:8.2-fpm-alpine
    container_name: apex360-php
    volumes:
      - ./generate-course.php:/var/www/html/generate-course.php
      - ./cursos:/var/www/html/cursos
    networks:
      - apex-network

networks:
  apex-network:
    driver: bridge
```

Y actualizar `nginx.conf` para procesar PHP:

```nginx
location ~ \.php$ {
    fastcgi_pass apex360-php:9000;
    fastcgi_index index.php;
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
}
```

---

## 🎯 **Recomendación**

### **Para Empezar Rápido (AHORA):**
👉 Usa **OPCIÓN 2** (descarga manual)
- No requiere cambios en Docker
- Funciona inmediatamente
- Simple de entender

### **Para Producción (DESPUÉS):**
👉 Implementa **OPCIÓN 1** (PHP automático)
- Configurar PHP-FPM
- Automatizar completamente
- Mejor experiencia de usuario

---

## 📝 **Cómo Funciona Actualmente**

El `otec-admin.html` actualizado hace esto:

```javascript
async function saveCourse() {
    // 1. Guardar en LocalStorage
    localStorage.setItem('otecCourses', JSON.stringify(courses));
    
    // 2. Intentar generar en servidor via PHP
    try {
        await fetch('generate-course.php', {
            method: 'POST',
            body: JSON.stringify({ course: courseData })
        });
        
        alert('✅ Curso creado. Archivo generado en /cursos/');
    } catch (error) {
        // 3. Si falla, descargar archivo
        downloadHTMLFile(htmlContent, filename);
        alert('📥 Curso creado. Descarga el HTML y súbelo a /cursos/');
    }
}
```

---

## 🔄 **Estado Actual del Sistema**

**SIN PHP (por ahora):**
- ✅ Cursos se guardan en LocalStorage
- ✅ HTML se genera
- ✅ Descarga automática
- ❌ Requiere subida manual

**CON PHP (futuro):**
- ✅ Cursos se guardan en LocalStorage
- ✅ HTML se genera
- ✅ **Archivo se crea automáticamente en /cursos/**
- ✅ Sin pasos manuales

---

## 🚀 **Para Implementar AHORA**

Si quieres usar la descarga manual (más simple):

```bash
# 1. Ya tienes otec-admin.html actualizado
# 2. Crear curso en el panel
# 3. Descargar HTML
# 4. Subir a /cursos/

# Listo ✅
```

Si quieres implementar PHP automático:

```bash
# 1. Instalar PHP en Docker (ver arriba)
# 2. Subir generate-course.php
# 3. Configurar nginx.conf para PHP
# 4. Rebuild Docker

# Listo ✅ (más complejo pero automático)
```

---

## 💡 **Alternativa: Sin Backend**

Si no quieres complicarte con PHP, otra opción es:

1. **Crear cursos en admin local**
2. **Descargar archivos HTML**
3. **Usar un script** que suba los archivos por SCP automáticamente

Puedo crear un script `upload-courses.sh` si prefieres.

---

## ✅ **Conclusión**

**Por ahora:**
- Usa el sistema de descarga manual
- Es simple y funciona
- No requiere cambios en Docker

**Para después:**
- Implementa PHP si quieres automatización completa
- O usa un script de upload automático
- O migra a un CMS real (WordPress, etc.)

¿Qué opción prefieres implementar?
