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
| Secret | `FTP_SERVER`     | `ftp.seusite.com`                    |
| Secret | `FTP_USERNAME`   | `usuario`                            |
| Secret | `FTP_PASSWORD`   | `…`                                  |
| Var    | `FTP_SERVER_DIR` | `/wp-content/plugins/nexo-player/` (precisa terminar com `/`) |
| Var    | `FTP_PORT`       | opcional (padrão 21)                 |
| Var    | `FTP_PROTOCOL`   | opcional (`ftp` ou `ftps`)           |

Sites sem environment configurado são pulados sem erro. Para adicionar um site,
crie o environment e acrescente o nome na `matrix.site` do workflow.
