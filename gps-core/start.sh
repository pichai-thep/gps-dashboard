#!/bin/bash

echo "🚀 Starting SSH tunnels..."

start_tunnel () {
  LOCAL_PORT=$1
  REMOTE_PORT=$2
  HOST=$3

  echo "→ Tunnel $LOCAL_PORT → $HOST:$REMOTE_PORT"
  pkill -f "autossh.*:$LOCAL_PORT:127.0.0.1:$REMOTE_PORT" 2>/dev/null

    autossh -M 0 -f -N \
      -p 22000 \
      -o ServerAliveInterval=30 \
      -o ServerAliveCountMax=3 \
      -o ExitOnForwardFailure=yes \
      -L ${LOCAL_PORT}:127.0.0.1:${REMOTE_PORT} \
      gpsroot@${HOST} \
      > tunnel_${LOCAL_PORT}.log 2>&1

  sleep 1

  # check port
  if lsof -i :${LOCAL_PORT} >/dev/null; then
    echo "✅ OK port ${LOCAL_PORT}"
  else
    echo "❌ FAIL port ${LOCAL_PORT}"
  fi
}

# 🔥 mapping (สำคัญ)
start_tunnel 7305 63005 202.129.206.54
start_tunnel 7310 63010 202.129.206.44
start_tunnel 7313 63013 202.129.206.48
start_tunnel 7314 63014 202.129.206.49
start_tunnel 7316 63016 202.129.206.50
start_tunnel 7319 63019 202.129.206.52
start_tunnel 7320 63020 202.129.206.53
start_tunnel 7321 63021 202.129.206.55

echo "🚀 Starting Laravel..."
php artisan serve --port=8000
