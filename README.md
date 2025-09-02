# TB Spotify Now Playing

Plugin de WordPress que muestra en tu sitio el tema que estás reproduciendo en Spotify (o el último reproducido).  
Funciona con Elementor mediante shortcode o directamente en widgets HTML.

## 🚀 Características
- Muestra carátula, track, artista y estado (*Reproduciendo ahora* o *Último reproducido*).  
- Se actualiza automáticamente cada 20 segundos.  
- Compatible con cualquier tema y constructor visual (Elementor, Gutenberg, etc.).  
- No requiere Spotify Premium (solo lectura de lo que escuchás).

## 📦 Instalación
1. Descargá el ZIP del plugin.  
2. En WordPress: **Plugins → Añadir nuevo → Subir plugin**.  
3. Activá **TB Spotify Now Playing**.  

## ⚙️ Configuración
1. Creá una **app** en [Spotify for Developers](https://developer.spotify.com/dashboard/).  
   - **App name**: el que quieras (ej: `Now Playing Widget`).  
   - **Website**: la URL de tu sitio (ej: `https://tusitio.com`).  
   - **Redirect URI**:  
     ```
     https://tusitio.com/wp-admin/options-general.php?page=tb-spotify-now-playing&tb_spotify_callback=1
     ```
   - Guardá y copiá el **Client ID** y **Client Secret**.

2. En WordPress: **Ajustes → TB Spotify Now Playing**.  
   - Pegá **Client ID** y **Client Secret**.  
   - Guardá.  
   - Clic en **Conectar con Spotify** y aceptá permisos.

3. Si todo está correcto, verás **Conectado**.

## 🖼️ Uso
- **Shortcode** (en Elementor → widget “Shortcode”):  
  ```
  [tb_spotify_now_playing]
  ```
- **HTML directo** (si preferís incrustar con JS):  
  ```html
  <div id="tb-spotify-np"></div>
  <script src=".../assets/js/now-playing.js"></script>
  ```

## 🎨 Estilos
El plugin incluye un CSS básico, pero podés personalizarlo:  
```css
.tb-spotify-np__track { font-size: 18px; font-weight: 700; }
.tb-spotify-np__artist { opacity: 0.8; }
.tb-spotify-np__cover img { width: 72px; height: 72px; border-radius: 12px; }
```

## 📜 Licencia
GPL-2.0 o posterior. Libre para usar, modificar y compartir.  

---

### ✨ Créditos
- Desarrollado por **Tony Baffoni** (productor musical, compositor).  
- Implementación técnica con ayuda de ChatGPT.
