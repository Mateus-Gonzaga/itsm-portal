#!/bin/sh
# Instala e configura o Zabbix Agent 2 (7.0) apontando para o Zabbix da Fourline.
# Uso (na VM, como root):  curl -fsSL http://192.168.0.30:8083/agente.sh | sudo sh
# Opcional: ZBX=IP_DO_ZABBIX  (padrão 192.168.0.30)
set -e
ZBX="${ZBX:-192.168.0.30}"

echo "==> Zabbix Agent 2 -> servidor ${ZBX}"
[ "$(id -u)" -eq 0 ] || { echo "Rode com sudo."; exit 1; }
. /etc/os-release

case "$ID" in
  ubuntu) PKG="zabbix-release_latest_7.0+ubuntu${VERSION_ID}_all.deb"; URL="https://repo.zabbix.com/zabbix/7.0/ubuntu/pool/main/z/zabbix-release/${PKG}" ;;
  debian) PKG="zabbix-release_latest_7.0+debian${VERSION_ID}_all.deb"; URL="https://repo.zabbix.com/zabbix/7.0/debian/pool/main/z/zabbix-release/${PKG}" ;;
  *) echo "Sistema ${ID} ${VERSION_ID} não suportado por este script."; exit 1 ;;
esac

cd /tmp
echo "==> Baixando repositório oficial (${ID} ${VERSION_ID})"
if command -v wget >/dev/null 2>&1; then wget -q "$URL" -O "$PKG"; else curl -fsSL "$URL" -o "$PKG"; fi
dpkg -i "$PKG" >/dev/null
apt-get update -qq
echo "==> Instalando zabbix-agent2"
DEBIAN_FRONTEND=noninteractive apt-get install -y -qq zabbix-agent2 >/dev/null

CONF=/etc/zabbix/zabbix_agent2.conf
sed -i "s/^Server=.*/Server=${ZBX}/; s/^ServerActive=.*/ServerActive=${ZBX}/" "$CONF"
sed -i "s/^Hostname=.*/# Hostname definido pelo sistema/" "$CONF"
grep -q '^HostnameItem=' "$CONF" || echo 'HostnameItem=system.hostname' >> "$CONF"

systemctl enable zabbix-agent2 >/dev/null 2>&1
systemctl restart zabbix-agent2

# Libera a porta do agente no firewall, se o ufw estiver ativo.
if command -v ufw >/dev/null 2>&1 && ufw status | grep -q "Status: active"; then
  ufw allow from "$ZBX" to any port 10050 proto tcp >/dev/null
  echo "==> Firewall: porta 10050 liberada para ${ZBX}"
fi

if systemctl is-active --quiet zabbix-agent2; then
  echo "==> OK: agente rodando. IP desta VM: $(hostname -I | awk '{print $1}')"
else
  echo "==> ERRO: o agente não subiu. Veja: journalctl -u zabbix-agent2 -n 30"; exit 1
fi
