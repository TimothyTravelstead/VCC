/**
 * PM2 Ecosystem Configuration for VCC Feed EventSource Server
 *
 * This configuration file defines how PM2 should run the EventSource server.
 *
 * Usage:
 *   pm2 start ecosystem.config.js
 *   pm2 status
 *   pm2 logs vccfeed-eventsource
 *   pm2 restart vccfeed-eventsource
 *   pm2 stop vccfeed-eventsource
 *   pm2 save  # Save current process list for auto-restart on reboot
 *
 * @author Claude Code
 * @date October 25, 2025
 */

module.exports = {
  apps: [{
    name: 'vccfeed-eventsource',
    script: './server.js',
    instances: 1,
    exec_mode: 'fork',
    autorestart: true,
    watch: false,
    max_memory_restart: '500M',
    env: {
      NODE_ENV: 'production',
      PORT: 3000,
      REDIS_HOST: '127.0.0.1',
      REDIS_PORT: 6379,
      DB_HOST: '127.0.0.1',
      DB_USER: 'dgqtkqjasj',
      DB_PASS: 'CXpskz9QXQ',
      DB_NAME: 'dgqtkqjasj',
      SESSION_PATH: '/home/1203785.cloudwaysapps.com/dgqtkqjasj/private_html'
    },
    error_file: '/home/master/logs/vccfeed-eventsource-error.log',
    out_file: '/home/master/logs/vccfeed-eventsource-out.log',
    log_date_format: 'YYYY-MM-DD HH:mm:ss Z',
    merge_logs: true,
    min_uptime: '10s',
    max_restarts: 10,
    restart_delay: 4000
  }]
};
