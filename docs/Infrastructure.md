# Infrastructure

Catatan infrastruktur project — server, CI/CD, domain. Bukan bagian PRD produk, tapi krusial untuk operasional.

## Topologi

```
Internet
   │
   ├── pve.bintangdemarta.web.id ──┐
   └── flightops.bintangdemarta.web.id ──┤
                                          │ Cloudflare Tunnel
                                          │ (cloudflared, jalan di Proxmox host)
                                          ▼
                              Proxmox VE (Lenovo ThinkCentre M710q)
                              192.168.1.230:8006
                                          │
                                          ├── VM 100 "Ubuntu-server" (192.168.1.29)
                                          │   └── Apache + PHP 8.3 + kml-converter
                                          │       /var/www/kml-converter
                                          │
                              Jaringan lokal: TP-Link, gateway 192.168.1.1
```

## Hardware Proxmox

Lenovo ThinkCentre M710q Tiny — Intel i3-7100 (2C/4T), 16GB DDR4, NVMe 512GB + HDD 500GB. Harga pasaran bekas ~Rp900rb-1jt (spek RAM sama, Agustus 2026).

## Migrasi Jaringan (Mikrotik → TP-Link)

Proxmox awalnya statis di `10.10.10.230/24` (gateway Mikrotik lama). Setelah ganti router ke TP-Link (`192.168.1.0/24`), IP lama sudah tidak ada — dimigrasikan ke `192.168.1.230/24` lewat edit `/etc/network/interfaces` + `ifreload -a`. Banner konsol fisik (`/etc/issue`) juga diupdate manual (teks statis, tidak auto-regenerate).

## Cloudflare Tunnel

Tunnel ID `35cdd469-ac3d-4296-85a7-c3839411037e`, jalan sebagai systemd service di Proxmox host (`/etc/cloudflared/config.yml`). Satu tunnel melayani 2 hostname:

```yaml
ingress:
  - hostname: pve.bintangdemarta.web.id
    service: https://localhost:8006
  - hostname: flightops.bintangdemarta.web.id
    service: http://192.168.1.29:80
  - service: http_status:404
```

Karena tunnel target `localhost`, migrasi jaringan lokal **tidak mempengaruhi** akses via domain — cuma akses IP langsung yang perlu diupdate manual.

## CI/CD

GitHub Actions **self-hosted runner** di VM 100 (bukan cloud runner) — karena VM di belakang NAT rumah tanpa IP publik, runner polling keluar ke GitHub (tidak perlu port forwarding).

- Trigger: push ke `main`
- Deploy: `git fetch` + `git reset --hard origin/main` (bukan `git pull` — lebih tangguh terhadap interupsi mid-deploy, lihat catatan insiden di bawah)
- Verifikasi: curl ke `localhost/index.php`

### Insiden: deploy gagal diam-diam

Reboot Proxmox sempat memotong 1 deploy job di tengah jalan, meninggalkan `git pull` dalam state campuran (sebagian file baru ter-tulis, tapi HEAD pointer belum update) — pull berikutnya gagal dengan pesan "local changes would be overwritten". Diperbaiki dengan ganti strategi deploy ke `git reset --hard` (selalu convergen ke state benar apapun kondisi sebelumnya) + perbaikan ownership folder `data/` (shared group `actions-runner:www-data`, supaya baik proses deploy maupun Apache runtime bisa nulis).

## Ownership Model VM 100

- `/var/www/kml-converter` — owner `actions-runner` (user khusus jalankan CI/CD)
- `/var/www/kml-converter/data/` — group `www-data` + setgid, shared write (Apache butuh tulis SQLite, git/CI butuh tulis file baru)

## Terkait

- [[Home]]
