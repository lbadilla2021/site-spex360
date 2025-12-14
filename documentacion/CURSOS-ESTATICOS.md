# 🎓 NUEVO SISTEMA DE CURSOS ESTÁTICOS - SEO Optimizado

## ✅ **Cambio Principal**

**ANTES (Dinámico - Malo para SEO):**
- Un solo archivo: `curso-detalle.html?id=1`
- Contenido cargado con JavaScript desde LocalStorage
- Google no puede indexar bien páginas dinámicas
- Cada curso tiene la misma URL base

**AHORA (Estático - Excelente para SEO):**
- Cada curso tiene su propio archivo HTML
- URLs únicas y descriptivas
- Google indexa perfectamente cada página
- Contenido HTML estático que los motores ven

---

## 📁 **Nueva Estructura**

```
apex360/
├── otec.html (landing cursos)
├── admin/otec-admin.html (panel administración)
└── cursos/ (📁 NUEVA CARPETA)
    ├── curso-google-sheets-avanzado.html
    ├── tecnicas-trabajo-alturas.html
    ├── power-bi-nivel-basico.html
    ├── power-bi-nivel-intermedio.html
    ├── excel-avanzado-empresas.html
    └── prevencion-riesgos-laborales.html
```

---

## 🔧 **Cómo Funciona Ahora**

### **1. Crear Curso en Admin**

En `admin/otec-admin.html`:
1. Click "+ Nuevo Curso"
2. Llenar formulario:
   - **Título:** "Curso de Google Sheets Avanzado"
   - **Duración:** "28 horas"
   - **Introducción:** "Domina Google Sheets..."
   - **Imagen URL:** (opcional)
   - **Fechas:** "Próximo inicio: 15 Feb 2025"
3. Agregar secciones (subtítulo + contenido)
4. Click "Guardar Curso"

### **2. Generación Automática**

El sistema automáticamente:

✅ **Genera nombre de archivo:**
- Título: "Curso de Google Sheets Avanzado"
- Elimina stopwords: "de"
- Slug: `curso-google-sheets-avanzado.html`

✅ **Crea HTML completo:**
- Header con navegación
- Hero con título y duración
- Todas las secciones formateadas
- CTA de inscripción
- Footer
- SEO meta tags

✅ **Descarga automática:**
- El navegador descarga el archivo `.html`
- Guardar en carpeta `cursos/`

### **3. Subir al Servidor**

```bash
# En tu VPS
cd /root/docker/site-apex/cursos

# Subir el archivo descargado
# (usa SFTP, SCP, o WinSCP)

# Reconstruir Docker
cd ..
docker-compose down
docker-compose build --no-cache
docker-compose up -d
```

---

## 🏷️ **Generación de Nombres de Archivo**

### **Stopwords Eliminadas:**
```javascript
['de', 'del', 'la', 'el', 'los', 'las', 'a', 'al', 
 'en', 'y', 'o', 'un', 'una', 'para', 'por', 
 'con', 'sin']
```

### **Ejemplos de Conversión:**

| Título Original | Archivo Generado |
|----------------|------------------|
| "Curso de Google Sheets Avanzado" | `curso-google-sheets-avanzado.html` |
| "Técnicas de Trabajo en Alturas" | `tecnicas-trabajo-alturas.html` |
| "Power BI Nivel Básico" | `power-bi-nivel-basico.html` |
| "Excel Avanzado para Empresas" | `excel-avanzado-empresas.html` |
| "Prevención de Riesgos Laborales" | `prevencion-riesgos-laborales.html` |
| "Capacitación en Manejo de Montacargas" | `capacitacion-manejo-montacargas.html` |

### **Proceso de Limpieza:**
1. Convertir a minúsculas
2. Eliminar acentos (á → a, é → e, etc.)
3. Dividir en palabras
4. Filtrar stopwords
5. Unir con guiones `-`
6. Eliminar caracteres especiales
7. Agregar `.html`

---

## 🌐 **URLs SEO-Friendly**

**Ejemplos de URLs generadas:**

```
https://apex360.cl/cursos/curso-google-sheets-avanzado.html
https://apex360.cl/cursos/tecnicas-trabajo-alturas.html
https://apex360.cl/cursos/power-bi-nivel-basico.html
https://apex360.cl/cursos/excel-avanzado-empresas.html
```

**Beneficios SEO:**
✅ URLs descriptivas (keywords en URL)
✅ HTML estático (100% indexable)
✅ Meta tags únicos por curso
✅ Title tag optimizado
✅ Meta description personalizada
✅ Contenido visible para crawlers
✅ Carga rápida (no JavaScript pesado)
✅ Structured data ready

---

## 📊 **Estructura del HTML Generado**

Cada archivo incluye:

```html
<!DOCTYPE html>
<html lang="es-CL">
<head>
    <title>Curso Google Sheets Avanzado | OTEC Apex</title>
    <meta name="description" content="Domina Google Sheets...">
    <!-- CSS completo inline -->
</head>
<body>
    <header>
        <nav><!-- Navegación completa --></nav>
    </header>
    
    <div class="course-detail">
        <a href="../otec.html">← Volver</a>
        
        <div class="course-header">
            <span class="course-badge">28 horas</span>
            <h1>Curso Google Sheets Avanzado</h1>
            <p class="course-intro-text">Domina Google Sheets...</p>
        </div>
        
        <div class="course-content">
            <div class="section-block">
                <h2>Fundamentos</h2>
                <p>Contenido de la sección...</p>
            </div>
            <!-- Más secciones... -->
        </div>
        
        <div class="cta-section">
            <h2>¿Listo para inscribirte?</h2>
            <a href="../apex360-landing.html#contacto">Inscripción</a>
        </div>
    </div>
    
    <footer><!-- Footer completo --></footer>
</body>
</html>
```

---

## 🔄 **Flujo Completo**

### **Crear Nuevo Curso:**

1. **Admin Panel** → Crear curso → Llenar datos → Guardar
2. **Navegador** → Descarga `nombre-curso.html`
3. **Guardar** archivo en `/cursos/`
4. **VPS** → Subir archivo a `/root/docker/site-apex/cursos/`
5. **Docker** → `docker-compose up -d --build`
6. **Probar** → `http://tu-ip:9500/cursos/nombre-curso.html`

### **Editar Curso Existente:**

1. **Admin Panel** → Click "Editar" → Modificar datos → Guardar
2. **Navegador** → Descarga HTML actualizado
3. **Reemplazar** archivo existente en `/cursos/`
4. **VPS** → Subir archivo actualizado
5. **Docker** → Rebuild si es necesario

---

## ⚠️ **Importante - LocalStorage Sigue Usado**

**Para qué se usa LocalStorage:**
- Guardar metadata de cursos (título, duración, intro, filename)
- Mostrar lista de cursos en `otec.html`
- Panel de administración (tabla de cursos)

**Lo que cambió:**
- ❌ Ya NO se usa para renderizar contenido de curso
- ✅ Ahora se generan archivos HTML estáticos
- ✅ Cada curso es una página independiente

---

## 📈 **Ventajas del Nuevo Sistema**

### **SEO:**
✅ Google indexa perfectamente cada curso
✅ URLs únicas y descriptivas
✅ Meta tags personalizados por curso
✅ Contenido HTML estático (no JavaScript)
✅ Títulos H1, H2 semánticos
✅ Velocidad de carga óptima

### **Rendimiento:**
✅ No depende de JavaScript para contenido
✅ Carga más rápida (HTML estático)
✅ Funciona aunque JavaScript esté deshabilitado
✅ Cacheable por CDN

### **Usabilidad:**
✅ Cada curso tiene su URL única para compartir
✅ Bookmarkable (se puede guardar en favoritos)
✅ Historial del navegador funciona correctamente
✅ Compatible con lectores de pantalla

---

## 🚀 **Para Desplegar**

```bash
# 1. En tu servidor, crear carpeta cursos
cd /root/docker/site-apex
mkdir -p cursos

# 2. Subir archivos HTML generados a /cursos/

# 3. Reconstruir Docker
docker-compose down
docker-compose build --no-cache
docker-compose up -d

# 4. Verificar
docker exec apex360-landing ls /usr/share/nginx/html/cursos/
```

---

## 📝 **Archivos Actualizados**

1. **admin/otec-admin.html** - Genera HTML automáticamente
2. **otec.html** - Enlaces a `/cursos/filename.html`
3. **Dockerfile** - Copia carpeta `/cursos/`
4. **Cursos de ejemplo** - Ahora incluyen campo `filename`

---

## 🎯 **Próximos Pasos Sugeridos**

1. ✅ Generar HTML para los 6 cursos de ejemplo
2. ✅ Subir archivos a carpeta `/cursos/`
3. ⚠️ Implementar sitemap XML automático
4. ⚠️ Agregar structured data (Schema.org)
5. ⚠️ Configurar canonical URLs
6. ⚠️ Implementar breadcrumbs

---

## 🆚 **Comparación Antes vs Ahora**

| Aspecto | Antes (Dinámico) | Ahora (Estático) |
|---------|------------------|------------------|
| **URL** | `/curso-detalle.html?id=1` | `/cursos/nombre-curso.html` |
| **SEO** | ❌ Malo | ✅ Excelente |
| **Indexación** | Parcial | Completa |
| **Velocidad** | Media | Rápida |
| **Compartible** | No (requiere ID) | Sí (URL única) |
| **JavaScript** | Requerido | Opcional |
| **Cacheable** | Limitado | Completo |

---

## ✨ **Conclusión**

Este nuevo sistema es **mucho mejor para SEO** y rendimiento. Cada curso ahora es una página HTML estática completamente indexable por Google, con URLs descriptivas y contenido semántico.

**El único paso extra** es subir manualmente los archivos HTML generados a la carpeta `/cursos/` en el servidor.
