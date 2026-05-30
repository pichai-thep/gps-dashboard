#!/bin/bash

echo "🚀 Starting SSH tunnels..."

SSH_USER="gpsroot"
SSH_PORT="22000"

start_tunnel () {
  TYPE=$1
  LOCAL_PORT=$2
  REMOTE_PORT=$3
  HOST=$4

  LOG_FILE="logs/tunnel_${TYPE}_${LOCAL_PORT}.log"

  echo "→ [$TYPE] localhost:${LOCAL_PORT} → ${HOST}:127.0.0.1:${REMOTE_PORT}"

  # kill tunnel เดิมของ local port นี้ก่อน
  pkill -f "autossh.*-L ${LOCAL_PORT}:127.0.0.1:${REMOTE_PORT}" 2>/dev/null

  autossh -M 0 -f -N \
    -p ${SSH_PORT} \
    -o ServerAliveInterval=30 \
    -o ServerAliveCountMax=3 \
    -o ExitOnForwardFailure=yes \
    -L ${LOCAL_PORT}:127.0.0.1:${REMOTE_PORT} \
    ${SSH_USER}@${HOST} \
    > ${LOG_FILE} 2>&1

  sleep 1

  if lsof -nP -iTCP:${LOCAL_PORT} -sTCP:LISTEN >/dev/null; then
    echo "✅ [$TYPE] OK port ${LOCAL_PORT}"
  else
    echo "❌ [$TYPE] FAIL port ${LOCAL_PORT}"
    cat ${LOG_FILE}
  fi
}

echo ""
echo "🔥 Starting MySQL tunnels..."

start_tunnel mysql 7305 63005 202.129.206.54
start_tunnel mysql 7310 63010 202.129.206.44
start_tunnel mysql 7313 63013 202.129.206.48
start_tunnel mysql 7314 63014 202.129.206.49
start_tunnel mysql 7316 63016 202.129.206.50
start_tunnel mysql 7319 63019 202.129.206.52
start_tunnel mysql 7320 63020 202.129.206.53
start_tunnel mysql 7321 63021 202.129.206.55

echo ""
echo "🔥 Starting Redis tunnels..."

# Redis remote port ส่วนใหญ่คือ 6379
# local port แนะนำใช้ 73xx แยกจาก MySQL ชัดเจน

start_tunnel redis 7405 6379 202.129.206.54
start_tunnel redis 7410 6379 202.129.206.44
start_tunnel redis 7413 6379 202.129.206.48
start_tunnel redis 7414 6379 202.129.206.49
start_tunnel redis 7416 6379 202.129.206.50
start_tunnel redis 7419 6379 202.129.206.52
start_tunnel redis 7420 6379 202.129.206.53
start_tunnel redis 7421 6379 202.129.206.55

echo ""
echo "🚀 Starting Laravel..."
php artisan serve --port=8000
