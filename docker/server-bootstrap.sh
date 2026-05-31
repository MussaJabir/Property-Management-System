#!/usr/bin/env bash
#
# One-time host bootstrap for the AlmaLinux 10 box that runs PMS alongside
# the existing nginx + Let's Encrypt setup. Idempotent — safe to re-run.
#
# What it does:
#   1. Installs Docker Engine + Compose plugin from Docker's official RHEL repo
#   2. Adds the `opc` user to the docker group so non-sudo commands work
#   3. Creates /opt/pms (image-baked deploys land here)
#   4. Generates an SSH deploy keypair for GitHub Actions (idempotent)
#   5. Prints the public key — paste it into ~/.ssh/authorized_keys yourself
#      OR add it as a GitHub Actions repo secret as instructed in DEPLOYMENT.md
#
# Run as: ./server-bootstrap.sh   (must be on the server, not on your laptop)

set -euo pipefail

if [[ "$EUID" -eq 0 ]]; then
  echo "Don't run as root. Run as opc; it will sudo where needed."
  exit 1
fi

echo "[1/5] Installing Docker Engine + Compose plugin…"
if ! command -v docker &> /dev/null; then
  sudo dnf -y install dnf-plugins-core
  sudo dnf config-manager --add-repo https://download.docker.com/linux/centos/docker-ce.repo
  sudo dnf -y install docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
  sudo systemctl enable --now docker
else
  echo "  docker already installed: $(docker --version)"
fi

echo "[2/5] Adding opc to docker group…"
if ! groups opc | grep -q '\bdocker\b'; then
  sudo usermod -aG docker opc
  echo "  ⚠  Log out and back in so the new group sticks before running docker commands."
fi

echo "[3/5] Creating /opt/pms working dir…"
sudo install -d -o opc -g opc -m 0755 /opt/pms

echo "[4/5] Generating GHA deploy SSH key (if missing)…"
KEY_PATH="$HOME/.ssh/gha_pms_deploy"
if [[ ! -f "$KEY_PATH" ]]; then
  ssh-keygen -t ed25519 -N "" -C "gha-pms-deploy@$(hostname)" -f "$KEY_PATH"
  # Authorize the public half so GHA can SSH in as opc.
  cat "$KEY_PATH.pub" >> "$HOME/.ssh/authorized_keys"
  chmod 600 "$HOME/.ssh/authorized_keys"
fi

echo "[5/5] SELinux note…"
# SELinux is Enforcing on this box. Docker's overlay2 driver is fine; bind
# mounts into containers can need :Z labels but we use named volumes only.
echo "  SELinux is Enforcing. Named volumes used by our compose file work as-is."

cat <<'EOF'

────────────────────────────────────────────────────────────────────
DONE.

Next steps (do these manually, NOT in this script):

1. Copy the GHA deploy PRIVATE key contents:
       cat ~/.ssh/gha_pms_deploy
   → paste as a GitHub Actions secret named  PROD_SSH_KEY  in the PMS repo
     (Settings → Secrets and variables → Actions → New repository secret)

2. Add two more GHA secrets:
       PROD_SSH_HOST = 40.233.127.121
       PROD_SSH_USER = opc

3. Verify Docker works (after logging out + back in):
       docker run --rm hello-world

4. Confirm Oracle Cloud Security List allows ingress on tcp/443 already
   (existing nginx serves https, so it almost certainly does — but tcp/80
   too, for cert renewal). No host firewalld changes needed: it's inactive.

5. Add a DNS A record  pms.bjptechnologies.co.tz → 40.233.127.121.

6. Once DNS propagates, on the server:
       sudo certbot --nginx -d pms.bjptechnologies.co.tz
   to provision the Let's Encrypt cert before the new nginx server block
   reverse-proxies to the PMS container.

7. Drop the nginx server block (docker/nginx-pms.conf in this repo) into
   /etc/nginx/conf.d/pms.conf and reload nginx.
────────────────────────────────────────────────────────────────────
EOF
