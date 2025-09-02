(function($){
  async function fetchNP(){
    try{
      const r = await fetch(TBSpotifyNP.ajaxurl + '?action=tb_np', { credentials: 'same-origin' });
      if(!r.ok) return;
      const payload = await r.json();
      if(!payload || !payload.success) return;

      const d = payload.data;
      const root = $('.tb-spotify-np');
      const cover = root.find('.tb-spotify-np__cover img');
      const track = root.find('.tb-spotify-np__track');
      const artist = root.find('.tb-spotify-np__artist');
      const state = root.find('.tb-spotify-np__state');

      if (d.album_art) {
        cover.attr('src', d.album_art).show();
      } else {
        cover.hide().attr('src','');
      }
      track.text(d.track || 'Sin reproducción');
      if (d.url) track.attr('href', d.url); else track.removeAttr('href');
      artist.text(d.artists || '');
      state.text(d.state_text || '');
    }catch(e){
      // silent
    }
  }
  $(document).ready(function(){
    fetchNP();
    setInterval(fetchNP, TBSpotifyNP.poll || 20000);
  });
})(jQuery);
