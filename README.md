# Nexo Player

Plugin WordPress: player de vídeo para posts — MP4 próprio ou embed de tubes,
com capa, marca d'água, vídeos relacionados, "continuar assistindo" e códigos
extras (ads/analytics).

## Deploy

Push na `main` → GitHub Actions sobe o plugin via FTP para os sites configurados
(`.github/workflows/deploy.yml`). Cada site é um **Environment** no GitHub
(Settings → Environments) com:

| Tipo   | Nome             | Exemplo                              |
|--------|------------------|--------------------------------------|
| Secret | `FTP_SERVER`     | IP/host de origem (domínio proxied pelo Cloudflare NÃO funciona) |
| Secret | `FTP_USERNAME`   | `usuario`                            |
| Secret | `FTP_PASSWORD`   | `…`                                  |
| Var    | `FTP_SERVER_DIR` | caminho completo no servidor, terminando com `/` — no SFTP é o caminho real, ex.: `/home/usuario/public_html/wp-content/plugins/nexo-player/` |
| Var    | `FTP_PORT`       | opcional (padrão: 22 no sftp, 21 no ftp/ftps) |
| Var    | `FTP_PROTOCOL`   | opcional: `sftp` (padrão), `ftp` ou `ftps` |

Sites sem environment configurado são pulados sem erro. Para adicionar um site,
crie o environment e acrescente o nome na `matrix.site` do workflow.
