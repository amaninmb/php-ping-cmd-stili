<?php
// index.php
?><!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Windows-like Ping Terminal</title>
<style>
  :root{ --bg:#000; --fg:#dfe6e9; --muted:#8b949e; --good:#9ee89b; --bad:#ff8b8b; --accent:#4caf50; }
  html,body{height:100%; margin:0; font-family: "Consolas", "Courier New", monospace; background:var(--bg); color:var(--fg);}
  .wrap{max-width:980px; margin:18px auto; padding:12px;}
  .terminal{background:linear-gradient(180deg,#000 0%, #050505 100%); border-radius:8px; padding:10px; min-height:520px; box-shadow:0 12px 40px rgba(0,0,0,0.7); border:1px solid rgba(255,255,255,0.03); display:flex; flex-direction:column;}
  .header{display:flex; gap:8px; align-items:center; padding:8px;}
  .controls{margin-left:auto; display:flex; gap:8px; align-items:center;}
  input[type=text]{background:transparent; border:1px solid rgba(255,255,255,0.06); color:var(--fg); padding:6px 10px; border-radius:6px; outline:none; min-width:260px;}
  button{background:var(--accent); border:none; color:#041; font-weight:700; padding:8px 12px; border-radius:6px; cursor:pointer;}
  button.stop{background:#e53935; color:#fff;}
  .screen{flex:1; overflow:auto; padding:14px; font-size:13px; line-height:1.4; white-space:pre-wrap; background:transparent; border-top:1px dashed rgba(255,255,255,0.03);}
  .muted{color:var(--muted); font-size:12px}
  .reply{color:var(--good)}
  .err{color:var(--bad)}
  .meta{color:#9ad; font-weight:600}
  .cursor { display:inline-block; width:8px; height:14px; background:var(--fg); margin-left:6px; animation:blink 1s steps(2) infinite; vertical-align:middle;}
  @keyframes blink { 50% { opacity:0 } }
</style>
</head>
<body>
<div class="wrap">
  <div class="terminal" role="region" aria-label="Ping terminal">
    <div class="header">
      <div style="font-weight:700">Windows Ping Terminal Stili <br> CMD nin olmadığı yerdeki Yardımcınız</div>
      <div class="controls">
        <input id="host" type="text" placeholder="örn: erapor.saglik.gov.tr veya 8.8.8.8" value="mehmetbagcivan.com" />
        <button id="start">BAŞLAT</button>
        <button id="stop" class="stop" disabled>DURDUR</button>
      </div>
    </div>

    <div class="screen" id="screen" aria-live="polite"></div>
    <div class="muted" style="padding:8px 0"><span id="status">Bağlı değil.</span></div>
  </div>
</div>

<script>
(function(){
  const screen = document.getElementById('screen');
  const status = document.getElementById('status');
  const startBtn = document.getElementById('start');
  const stopBtn = document.getElementById('stop');
  const hostInput = document.getElementById('host');
  let es = null;

  function addLine(text, cls){
    const div = document.createElement('div');
    if(cls) div.className = cls;
    div.textContent = text;
    screen.appendChild(div);
    screen.scrollTop = screen.scrollHeight;
    return div;
  }

  function addHTML(html){
    const div = document.createElement('div');
    div.innerHTML = html;
    screen.appendChild(div);
    screen.scrollTop = screen.scrollHeight;
    return div;
  }

  function clearScreen(){
    screen.innerHTML = '';
  }

  startBtn.addEventListener('click', function(){
    const host = hostInput.value.trim();
    if(!host){ alert('Lütfen host veya IP girin.'); return; }
    if(!/^[A-Za-z0-9\.\-]+$/.test(host)){ alert('Geçersiz karakterler. Sadece harf, rakam, nokta ve tire kullanılabilir.'); return; }

    clearScreen();
    addLine('PING başlıyor: ' + host, 'muted');
    status.textContent = 'Bağlanıyor...';
    startBtn.disabled = true;
    stopBtn.disabled = false;
    hostInput.disabled = true;

    const url = 'ping_stream.php?host=' + encodeURIComponent(host);
    es = new EventSource(url);

    es.onmessage = function(e){
      // Normal output satırı
      const txt = e.data;
      // Eğer "Reply from" içeriyorsa vurgula
      if(/^Reply from/i.test(txt) || /^Pinging/i.test(txt) || /^Packets:/i.test(txt) || /^Ping request could not find host/i.test(txt)) {
        // özel renklendirme
        if(/^Reply from/i.test(txt)){
          // Ayrıca RTT'yi ayıkla ve ayrı göster
          const m = txt.match(/Reply from\s+([\d\.]+):\s+bytes=(\d+)\s+time[=<]\s*([\dms]+)\s+TTL=(\d+)/i);
          if(m){
            const ip = m[1], bytes = m[2], time = m[3], ttl = m[4];
            addHTML(`<span class="reply">${txt}</span> <span class="meta">[RTT: ${time} TTL:${ttl}]</span>`);
            return;
          }
          addLine(txt, 'reply');
        } else if(/could not find host/i.test(txt) || /Request timed out/i.test(txt) || /Destination host unreachable/i.test(txt)){
          addLine(txt, 'err');
        } else {
          addLine(txt, 'muted');
        }
      } else {
        addLine(txt);
      }
    };

    es.addEventListener('meta', function(e){
      // meta eventleri JSON içerir
      try {
        const obj = JSON.parse(e.data);
        if(obj.ip) addLine('Hedef IP: ' + obj.ip, 'meta');
        if(obj.note) addLine(obj.note, 'muted');
      } catch(err){
        // fallback
        addLine(e.data, 'muted');
      }
    });

    es.onerror = function(e){
      // bağlantı hatası
      addLine('EventSource hata veya bağlantı kapandı.', 'muted');
      status.textContent = 'Bağlantı yok.';
      startBtn.disabled = false;
      stopBtn.disabled = true;
      hostInput.disabled = false;
      if(es){ es.close(); es = null; }
    };

    es.onopen = function(){
      status.textContent = 'Bağlandı — ping akışı alınmaya başlandı.';
    };
  });

  stopBtn.addEventListener('click', function(){
    if(es){
      es.close();
      es = null;
      addLine('--- durduruldu ---', 'muted');
      status.textContent = 'Durduruldu.';
    }
    startBtn.disabled = false;
    stopBtn.disabled = true;
    hostInput.disabled = false;
  });

})();
</script>
</body>

<br></br>
<center> Yapan Eden : <a href="https://mehmetbagcivan.com/" target="_self"> Mehmet BAĞCIVAN </a> </center>
</html>
