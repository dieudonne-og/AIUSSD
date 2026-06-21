<?php
// Browser-based USSD simulator. Posts the accumulated input string to ussd.php
// and shows the CON/END screen like a basic phone would.
?>
<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>USSD Simulator</title>
<style>
 body{font-family:system-ui;background:#222;display:flex;justify-content:center;padding:30px}
 .phone{width:300px;background:#000;border-radius:24px;padding:18px;color:#cfc}
 .screen{background:#04210a;min-height:260px;padding:14px;border-radius:8px;white-space:pre-wrap;font-size:14px}
 .end{color:#fc8}
 input,button{font-size:16px;padding:8px;margin-top:8px;width:100%;box-sizing:border-box}
 button{background:#1b5;color:#fff;border:0;border-radius:6px;cursor:pointer}
 .reset{background:#555}
</style></head><body>
<div class="phone">
  <div class="screen" id="screen">Press Start to dial *123#</div>
  <input id="reply" placeholder="Enter choice number" autocomplete="off">
  <button onclick="send()">Send</button>
  <button class="reset" onclick="reset()">Start / Reset</button>
</div>
<script>
let text = '', sessionId = '';
async function call() {
  const body = new URLSearchParams({sessionId, text});
  const r = await fetch('ussd.php', {method:'POST', body});
  const out = await r.text();
  const screen = document.getElementById('screen');
  screen.textContent = out.replace(/^(CON|END) /, '');
  screen.className = 'screen' + (out.startsWith('END') ? ' end' : '');
  if (out.startsWith('END')) text = ''; // session over
}
function reset() {
  text = ''; sessionId = 'sim-' + Date.now();
  document.getElementById('reply').value = '';
  call();
}
function send() {
  const v = document.getElementById('reply').value.trim();
  if (v === '') return;
  text = text === '' ? v : text + '*' + v;
  document.getElementById('reply').value = '';
  call();
}
reset();
</script>
</body></html>
