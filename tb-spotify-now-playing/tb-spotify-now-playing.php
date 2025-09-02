<?php
/**
 * Plugin Name: TB Spotify Now Playing
 * Description: Muestra en tu sitio (vía shortcode o Elementor) el tema de Spotify que estás reproduciendo ahora, con fallback al último reproducido.
 * Version: 1.0.0
 * Author: Tony Baffoni + ChatGPT
 */

if (!defined('ABSPATH')) { exit; }

class TB_Spotify_Now_Playing {
  private static $instance = null;
  private $opt_client_id_key = 'tb_spotify_client_id';
  private $opt_client_secret_key = 'tb_spotify_client_secret';
  private $opt_refresh_token_key = 'tb_spotify_refresh_token';

  public static function instance() {
    if (self::$instance === null) { self::$instance = new self(); }
    return self::$instance;
  }

  private function __construct() {
    add_action('admin_menu', [$this, 'admin_menu']);
    add_action('admin_init', [$this, 'handle_oauth_callback']);
    add_action('wp_enqueue_scripts', [$this, 'register_assets']);
    add_shortcode('tb_spotify_now_playing', [$this, 'shortcode']);
    add_action('wp_ajax_tb_np', [$this, 'ajax_now_playing']);
    add_action('wp_ajax_nopriv_tb_np', [$this, 'ajax_now_playing']);
  }

  public function register_assets() {
    wp_register_script('tb-spotify-np', plugins_url('assets/js/now-playing.js', __FILE__), ['jquery'], '1.0.0', true);
    wp_register_style('tb-spotify-np', plugins_url('assets/css/now-playing.css', __FILE__), [], '1.0.0');
  }

  public function admin_menu() {
    add_options_page('TB Spotify Now Playing', 'TB Spotify Now Playing', 'manage_options', 'tb-spotify-now-playing', [$this, 'settings_page']);
  }

  private function redirect_uri() {
    // Callback dentro del admin para capturar el "code"
    return admin_url('options-general.php?page=tb-spotify-now-playing&tb_spotify_callback=1');
  }

  public function settings_page() {
    if (!current_user_can('manage_options')) { return; }

    if (isset($_POST['tb_spotify_save']) && check_admin_referer('tb_spotify_save_nonce')) {
      update_option($this->opt_client_id_key, sanitize_text_field($_POST['tb_spotify_client_id'] ?? ''));
      update_option($this->opt_client_secret_key, sanitize_text_field($_POST['tb_spotify_client_secret'] ?? ''));
      echo '<div class="updated"><p>Guardado.</p></div>';
    }

    $client_id = esc_attr(get_option($this->opt_client_id_key, ''));
    $client_secret = esc_attr(get_option($this->opt_client_secret_key, ''));
    $refresh_token = get_option($this->opt_refresh_token_key, '');
    $connected = !empty($refresh_token);
    $redirect_uri_raw = $this->redirect_uri();
    $redirect_uri = esc_url($redirect_uri_raw);
    $scopes = urlencode('user-read-currently-playing user-read-recently-played');

    $authorize_url = 'https://accounts.spotify.com/authorize?response_type=code'
      . '&client_id=' . urlencode($client_id)
      . '&scope=' . $scopes
      . '&redirect_uri=' . urlencode($redirect_uri_raw)
      . '&show_dialog=true';

    ?>
    <div class="wrap">
      <h1>TB Spotify Now Playing</h1>
      <p>Configurá tu conexión con Spotify y usá el shortcode <code>[tb_spotify_now_playing]</code> en Elementor (widget "Shortcode").</p>

      <form method="post">
        <?php wp_nonce_field('tb_spotify_save_nonce'); ?>
        <table class="form-table" role="presentation">
          <tr>
            <th scope="row"><label for="tb_spotify_client_id">Client ID</label></th>
            <td><input name="tb_spotify_client_id" id="tb_spotify_client_id" type="text" class="regular-text" value="<?php echo $client_id; ?>" required></td>
          </tr>
          <tr>
            <th scope="row"><label for="tb_spotify_client_secret">Client Secret</label></th>
            <td><input name="tb_spotify_client_secret" id="tb_spotify_client_secret" type="password" class="regular-text" value="<?php echo $client_secret; ?>" required></td>
          </tr>
          <tr>
            <th scope="row">Redirect URI</th>
            <td><code><?php echo $redirect_uri; ?></code><p class="description">Pegá este valor exactamente en el panel de Spotify Developers.</p></td>
          </tr>
        </table>
        <p><input type="submit" name="tb_spotify_save" class="button button-primary" value="Guardar"></p>
      </form>

      <hr/>
      <h2>Estado de conexión</h2>
      <p style='opacity:.8'>Diagnóstico rápido: Client ID: <code><?php echo $client_id ? 'OK' : 'VACÍO'; ?></code> · Client Secret: <code><?php echo $client_secret ? 'OK' : 'VACÍO'; ?></code> · Refresh Token: <code><?php echo $refresh_token ? 'OK' : 'VACÍO'; ?></code></p>
      <?php if ($connected): ?>
        <p><span style="color:green;font-weight:bold;">Conectado</span>. Si querés reconectar (por ejemplo, cambiaste permisos), hacé clic abajo.</p>
      <?php else: ?>
        <p><span style="color:#cc0000;font-weight:bold;">No conectado</span>. Guardá Client ID/Secret y luego conectá.</p>
      <?php endif; ?>
      <p><a class="button button-secondary" href="<?php echo esc_url($authorize_url); ?>">Conectar con Spotify</a></p>
    </div>
    <?php
  }

  public function handle_oauth_callback() {
    if (!is_admin()) { return; }
    if (!current_user_can('manage_options')) { return; }
    if (!isset($_GET['page']) || $_GET['page'] !== 'tb-spotify-now-playing') { return; }
    if (!isset($_GET['tb_spotify_callback'])) { return; }

    if (isset($_GET['error'])) {
      add_action('admin_notices', function() {
        echo '<div class="error"><p>Error al conectar con Spotify.</p></div>';
      });
      return;
    }

    if (!isset($_GET['code'])) { return; }

    $code = sanitize_text_field($_GET['code']);
    $client_id = get_option($this->opt_client_id_key, '');
    $client_secret = get_option($this->opt_client_secret_key, '');
    if (empty($client_id) || empty($client_secret)) { return; }

    $response = wp_remote_post('https://accounts.spotify.com/api/token', [
      'headers' => [
        'Authorization' => 'Basic ' . base64_encode($client_id . ':' . $client_secret),
        'Content-Type'  => 'application/x-www-form-urlencoded',
      ],
      'body' => [
        'grant_type'   => 'authorization_code',
        'code'         => $code,
        'redirect_uri' => $this->redirect_uri(),
      ],
      'timeout' => 20,
    ]);

    if (is_wp_error($response)) {
      add_action('admin_notices', function() {
        echo '<div class="error"><p>No se pudo intercambiar el código por tokens.</p></div>';
      });
      return;
    }

    $code = wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);
    if ($code !== 200 || empty($body['refresh_token'])) {
      add_action('admin_notices', function() {
        echo '<div class="error"><p>Respuesta inválida de Spotify. Verificá Client ID/Secret y Redirect URI.</p></div>';
      });
      return;
    }

    update_option($this->opt_refresh_token_key, $body['refresh_token']);
    add_action('admin_notices', function() {
      echo '<div class="updated"><p>¡Conexión completada! Ya podés usar el shortcode.</p></div>';
    });
  }

  private function get_access_token() {
    $client_id = get_option($this->opt_client_id_key, '');
    $client_secret = get_option($this->opt_client_secret_key, '');
    $refresh_token = get_option($this->opt_refresh_token_key, '');
    if (empty($client_id) || empty($client_secret) || empty($refresh_token)) {
      return new WP_Error('tb_missing_conf', 'Faltan credenciales.');
    }

    // Usamos transients para evitar pedir tokens en cada request
    $cached = get_transient('tb_spotify_access_token');
    if ($cached) { return $cached; }

    $response = wp_remote_post('https://accounts.spotify.com/api/token', [
      'headers' => [
        'Authorization' => 'Basic ' . base64_encode($client_id . ':' . $client_secret),
        'Content-Type'  => 'application/x-www-form-urlencoded',
      ],
      'body' => [
        'grant_type'    => 'refresh_token',
        'refresh_token' => $refresh_token,
      ],
      'timeout' => 20,
    ]);

    if (is_wp_error($response)) {
      return $response;
    }
    $code = wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);
    if ($code !== 200 || empty($body['access_token'])) {
      return new WP_Error('tb_token_error', 'No se pudo obtener access token.');
    }
    $access_token = $body['access_token'];
    $expires_in = intval($body['expires_in'] ?? 3600);
    set_transient('tb_spotify_access_token', $access_token, max(60, $expires_in - 60));
    return $access_token;
  }

  private function api_get($url) {
    $token = $this->get_access_token();
    if (is_wp_error($token)) return $token;
    $response = wp_remote_get($url, [
      'headers' => ['Authorization' => 'Bearer ' . $token],
      'timeout' => 20,
    ]);
    return $response;
  }

  public function ajax_now_playing() {
    // Llamado por front-end (polling)
    $r = $this->api_get('https://api.spotify.com/v1/me/player/currently-playing');
    if (is_wp_error($r)) {
      wp_send_json_error(['message' => $r->get_error_message()], 500);
    }

    $status = wp_remote_retrieve_response_code($r);

    if ($status === 204) {
      // Nada sonando -> fallback a Recently Played
      $rp = $this->api_get('https://api.spotify.com/v1/me/player/recently-played?limit=1');
      if (is_wp_error($rp)) {
        wp_send_json_error(['message' => $rp->get_error_message()], 500);
      }
      $data = json_decode(wp_remote_retrieve_body($rp), true);
      $item = $data['items'][0]['track'] ?? null;
      if (!$item) {
        wp_send_json_success(['is_playing' => false]);
      } else {
        wp_send_json_success([
          'is_playing' => false,
          'progress_ms' => 0,
          'duration_ms' => $item['duration_ms'] ?? null,
          'track' => $item['name'] ?? '',
          'artists' => isset($item['artists']) ? implode(', ', wp_list_pluck($item['artists'], 'name')) : '',
          'album' => $item['album']['name'] ?? '',
          'album_art' => $item['album']['images'][0]['url'] ?? '',
          'url' => $item['external_urls']['spotify'] ?? '',
          'state_text' => 'Último reproducido',
        ]);
      }
      wp_die();
    }

    $body = wp_remote_retrieve_body($r);
    if (empty($body)) {
      wp_send_json_success(['is_playing' => false]);
      wp_die();
    }
    $data = json_decode($body, true);
    $item = $data['item'] ?? null;
    if (!$item) {
      wp_send_json_success(['is_playing' => false]);
      wp_die();
    }
    $artists = isset($item['artists']) ? implode(', ', wp_list_pluck($item['artists'], 'name')) : '';
    wp_send_json_success([
      'is_playing' => !empty($data['is_playing']),
      'progress_ms' => $data['progress_ms'] ?? 0,
      'duration_ms' => $item['duration_ms'] ?? null,
      'track' => $item['name'] ?? '',
      'artists' => $artists,
      'album' => $item['album']['name'] ?? '',
      'album_art' => $item['album']['images'][0]['url'] ?? '',
      'url' => $item['external_urls']['spotify'] ?? '',
      'state_text' => !empty($data['is_playing']) ? 'Reproduciendo ahora' : 'Pausado',
    ]);
    wp_die();
  }

  public function shortcode($atts) {
    wp_enqueue_script('tb-spotify-np');
    wp_enqueue_style('tb-spotify-np');
    wp_localize_script('tb-spotify-np', 'TBSpotifyNP', [
      'ajaxurl' => admin_url('admin-ajax.php'),
      'poll' => 20000,
    ]);

    ob_start(); ?>
      <div class="tb-spotify-np">
        <div class="tb-spotify-np__cover"><img alt="" /></div>
        <div class="tb-spotify-np__meta">
          <a class="tb-spotify-np__track" target="_blank" rel="noopener"></a>
          <div class="tb-spotify-np__artist"></div>
          <div class="tb-spotify-np__state"></div>
        </div>
      </div>
    <?php
    return ob_get_clean();
  }
}

TB_Spotify_Now_Playing::instance();
