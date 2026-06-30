# SLA Alert — GLPI Plugin

Plugin para GLPI 11 desarrollado por el **SOC Team de Linktic**. Envía alertas automáticas vía webhook a **Google Spaces** cuando un SLA u OLA está próximo a vencer o ya venció (breach).

## Funcionalidades

- **Alertas previas al vencimiento** — notifica con anticipación configurable para SLA TTO, SLA TTR, OLA TTO y OLA TTR
- **Alertas de breach** — notifica una sola vez cuando el SLA/OLA ya venció
- **Webhook Google Spaces** — integración directa con la API de Google Chat
- **Variables en mensajes** — personaliza el texto con `{ticket_id}`, `{ticket_name}`, `{tiempo_restante}`, `{tiempo_vencido}`
- **UI Bootstrap 5** — panel de configuración con cards por sección, toggles y vista clara por tipo de alerta

## Compatibilidad

| GLPI | Plugin |
|------|--------|
| ~11.0 | 1.0.0 |

## Instalación

### Opción 1 — Desde repositorio

```bash
cd /var/www/glpi/plugins
git clone https://github.com/socanalitica-droid/slaalert.git
```

### Opción 2 — Manual

1. Descarga o clona este repositorio
2. Copia la carpeta `slaalert/` dentro de `/var/www/glpi/plugins/`
3. En GLPI: **Configuración → Plugins → SLA Alert → Instalar → Activar**

## Estructura

```
slaalert/
├── setup.php               # Registro de hooks y cron
├── hook.php                # Install / uninstall (crea tabla de config)
├── inc/
│   ├── config.class.php    # Lectura y escritura de configuración
│   └── slaalert.class.php  # Lógica de evaluación SLA/OLA y envío webhook
└── front/
    └── config.form.php     # UI de configuración (Bootstrap 5 cards)
```

## Configuración

1. Ve a **Plugins → SLA Alert** en el menú de GLPI
2. Ingresa la **URL del Webhook** de tu espacio en Google Spaces
3. Para cada sección (SLA TTO, SLA TTR, OLA TTO, OLA TTR):
   - Activa la alerta previa e ingresa el mensaje con las variables disponibles
   - Activa la alerta de breach e ingresa el mensaje correspondiente
4. Guarda

### Variables disponibles en mensajes

| Variable | Descripción |
|----------|-------------|
| `{ticket_id}` | ID del ticket |
| `{ticket_name}` | Título del ticket |
| `{tiempo_restante}` | Tiempo que falta para vencer |
| `{tiempo_vencido}` | Tiempo transcurrido desde el vencimiento |

### Ejemplo de mensajes

```
⚠️ Ticket #{ticket_id} — {ticket_name} vence en {tiempo_restante}
🚨 Ticket #{ticket_id} — {ticket_name} lleva {tiempo_vencido} vencido
```

## Autor

SOC Team — Linktic  
Licencia: GPL v2+
