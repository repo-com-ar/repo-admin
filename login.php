<?php
// Si ya tiene sesión válida, redirigir al panel
require_once __DIR__ . '/lib/auth_check.php';
if (authUser()) {
    header('Location: index.php');
    exit;
}
?><!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Repo Admin — Iniciar sesión</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      background: #f1f5f9; min-height: 100vh;
      display: flex; align-items: center; justify-content: center; padding: 24px;
    }
    .card {
      background: #fff; border-radius: 16px; padding: 40px 36px;
      width: 100%; max-width: 380px;
      box-shadow: 0 4px 24px rgba(0,0,0,.08);
    }
    .logo { text-align: center; font-size: 1.5rem; font-weight: 800; color: #f97316; margin-bottom: 8px; }
    .subtitle { text-align: center; font-size: .85rem; color: #94a3b8; margin-bottom: 32px; }
    .form-group { margin-bottom: 18px; }
    label { display: block; font-size: .82rem; font-weight: 600; color: #475569; margin-bottom: 6px; }
    input {
      width: 100%; padding: 11px 14px; border: 1.5px solid #e2e8f0; border-radius: 10px;
      font-size: .95rem; color: #1e293b; transition: border-color .15s;
      outline: none;
    }
    input:focus { border-color: #f97316; }
    .btn {
      width: 100%; padding: 13px; border: none; border-radius: 50px; cursor: pointer;
      font-size: 1rem; font-weight: 700; letter-spacing: .01em;
      background: linear-gradient(135deg, #f97316, #ff9f1c);
      color: #fff; margin-top: 8px;
      box-shadow: 0 4px 14px rgba(249,115,22,.35);
      transition: transform .1s, box-shadow .1s;
    }
    .btn:active { transform: scale(.97); }
    .btn:disabled { opacity: .6; pointer-events: none; }
    .error-msg {
      background: #fef2f2; border: 1px solid #fecaca; color: #dc2626;
      border-radius: 8px; padding: 10px 14px; font-size: .85rem;
      margin-bottom: 16px; display: none;
    }
    .error-msg.show { display: block; }
  </style>
</head>
<body>
  <div class="card">
    <div class="logo">🛒 Repo Admin</div>
    <div class="subtitle">Ingresá con tu correo y contraseña</div>

    <div class="error-msg" id="errorMsg"></div>
    <div class="error-msg" id="diagnosticoMsg" style="background:#fef9c3;border:1px solid #fde68a;color:#b45309;display:none;"></div>
    <script>
      // Diagnóstico de soporte de cookies y fetch con credenciales
      function diagnosticoCookies() {
        var diag = [];
        // Soporte básico de cookies
        if (navigator.cookieEnabled) {
          diag.push('✔️ Cookies habilitadas en el navegador.');
        } else {
          diag.push('❌ Las cookies están deshabilitadas en el navegador.');
        }
        // Intentar setear, leer y borrar una cookie temporal
        var testValue = 'valor_' + Math.floor(Math.random() * 100000);
        // Expira en 1 hora
        var expires = new Date(Date.now() + 60 * 60 * 1000).toUTCString();
        document.cookie = 'testcookie=' + testValue + '; path=/; expires=' + expires;
        var cookies = document.cookie.split(';').map(x => x.trim());
        var found = cookies.find(x => x.startsWith('testcookie='));
        if (found) {
          var val = found.split('=')[1];
          if (val === testValue) {
            diag.push('✔️ El navegador permite crear y leer cookies vía JavaScript. Valor leído: ' + val + ' (visible en el inspector por 5 minutos)');
          } else {
            diag.push('❌ La cookie fue creada pero el valor leído no coincide. Valor leído: ' + val);
          }
          // No borrar la cookie de prueba inmediatamente
        } else {
          diag.push('❌ El navegador NO permite crear/leer cookies vía JavaScript.');
        }
        // Probar fetch con credenciales
        fetch('api/auth', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ correo: 'diagnostico', contrasena: 'diagnostico' }),
          credentials: 'include'
        }).then(r => {
          if (r.headers.get('set-cookie')) {
            diag.push('✔️ El backend intenta setear cookies vía fetch.');
          } else {
            diag.push('ℹ️ El backend no envió set-cookie en esta respuesta (esto es normal si las credenciales son inválidas).');
          }
          mostrarDiagnostico(diag);
        }).catch(e => {
          diag.push('❌ Error al probar fetch con credenciales: ' + e);
          mostrarDiagnostico(diag);
        });
      }
      function mostrarDiagnostico(diag) {
        var el = document.getElementById('diagnosticoMsg');
        el.innerHTML = '<b>Diagnóstico de cookies:</b><br>' + diag.join('<br>');
        el.style.display = 'block';
      }
      // Ejecutar diagnóstico automáticamente si hay error de cookie
      if (window.location.search.includes('error=1')) {
        diagnosticoCookies();
      }
    </script>
    <script>
      // Mostrar error si viene ?error=1 o ?error=2 en la URL
      if (window.location.search.includes('error=1')) {
        document.getElementById('errorMsg').textContent = 'No se pudo iniciar sesión: la cookie de autenticación no se guardó o fue rechazada por el navegador. Verifica configuración de cookies, SameSite y secure.';
        document.getElementById('errorMsg').classList.add('show');
      }
      if (window.location.search.includes('error=2')) {
        document.getElementById('errorMsg').textContent = 'No se pudo iniciar sesión: la cookie de autenticación existe pero el token es inválido o expiró. Intenta iniciar sesión nuevamente.';
        document.getElementById('errorMsg').classList.add('show');
      }
    </script>

    <form id="loginForm" onsubmit="return false">
      <div class="form-group">
        <label for="correo">Correo electrónico</label>
        <input type="email" id="correo" placeholder="correo@ejemplo.com" required autocomplete="email">
      </div>
      <div class="form-group">
        <label for="contrasena">Contraseña</label>
        <input type="password" id="contrasena" placeholder="••••••••" required autocomplete="current-password">
      </div>
      <button class="btn" id="btnLogin" onclick="login()">Ingresar</button>
    </form>
  </div>

  <script>
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Enter') login();
    });

    async function login() {
      var correo     = document.getElementById('correo').value.trim();
      var contrasena = document.getElementById('contrasena').value.trim();
      var errEl      = document.getElementById('errorMsg');
      var btn        = document.getElementById('btnLogin');

      if (!correo || !contrasena) {
        showError('Completá todos los campos');
        return;
      }

      btn.disabled    = true;
      btn.textContent = 'Ingresando...';
      errEl.classList.remove('show');

      try {
        var res  = await fetch('api/auth', {
          method:  'POST',
          headers: { 'Content-Type': 'application/json' },
          body:    JSON.stringify({ correo, contrasena }),
          credentials: 'include', // Permite el uso de cookies de sesión
        });
        let text = await res.text();
        let data;
        try {
          data = JSON.parse(text);
        } catch (e) {
          console.error('Respuesta no es JSON:', text);
          showError('Respuesta inesperada del servidor.');
          btn.disabled    = false;
          btn.textContent = 'Ingresar';
          return;
        }
          if (data.ok) {
            // Pausa de 2 segundos para ver el mensaje antes de redirigir
            setTimeout(function() {
              window.location.href = 'index.php';
            }, 2000);
          } else {
            btn.disabled    = false;
            btn.textContent = 'Ingresar';
          }
      } catch (err) {
        showError('Error de conexión. Intentá de nuevo.');
        btn.disabled    = false;
        btn.textContent = 'Ingresar';
        console.error('Error fetch login:', err);
      }
    }

    function showError(msg) {
      var el = document.getElementById('errorMsg');
      el.textContent = msg;
      el.classList.add('show');
    }
  </script>
</body>
</html>
