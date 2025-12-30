const express = require('express');
const fs = require('fs');
const readline = require('readline');

const app = express();
const PORT = 7777;
const LOG_FILE = '/var/log/nginx/access.log';

const excludedIPs = new Set(['127.0.0.1', '::1', '192.168.1.254']);
const trustedBots = ['uptimerobot', 'googlebot', 'bingbot', 'duckduckbot', 'pingdom', 'yandex', 'facebookexternalhit', 'discordbot'];
const knownScanners = ['curl', 'wget', 'python-requests', 'go-http-client', 'libwww', 'nikto', 'nmap', 'masscan', 'zgrab', 'sqlmap', 'acunetix', 'nessus'];
const aggressiveScanners = ['genomerawlerd', 'assetnote', 'censys', 'aquatone', 'httpx', 'shodan', 'cypex', 'acunetix'];
const suspiciousPaths = ['/wp-login', '/wp-admin', '/xmlrpc.php', '/.env', '/.git', '/phpmyadmin', '/admin', '/login', '/config.php', '/actuator/', '/+cscol+', '/+cscoe+', '/cgi-bin/', '/sdk/'];
const suspiciousMethods = new Set(['OPTIONS', 'TRACE', 'PUT', 'DELETE', 'CONNECT']);

const ipStats = {};

function scoreRequest({ method, path, ua, raw, statusCode }) {
  let score = 0;
  const uaLower = ua.toLowerCase();
  const pathLower = path.toLowerCase();

  if (!raw.includes('HTTP/')) score += 150;
  if (suspiciousMethods.has(method)) score += 30;
  if (!ua || ua.length < 8) score += 50;
  if (/^[a-z]+-\d+\.\d+\.\d+/.test(ua) || /^(ssh|rdp|ftp)-/i.test(ua)) score += 100;
  if (knownScanners.some(s => uaLower.includes(s))) score += 90;
  if (aggressiveScanners.some(s => uaLower.includes(s))) score += 150;
  if (/mozilla\/4\.0|msie|windows nt [5-6]|android\s\d{3,}/i.test(ua)) score += 70;
  if (suspiciousPaths.some(p => pathLower.includes(p)) || pathLower.endsWith('.php') || pathLower.endsWith('.jar')) score += 85;
  if (statusCode >= 400) score += 25;

  return score;
}

app.get('/stats', async (req, res) => {
  const stats = { humans: 0, cleanBots: 0, scanners: 0, exploits: 0 };
  const processedIPs = new Set();

  try {
    const rl = readline.createInterface({
      input: fs.createReadStream(LOG_FILE),
      crlfDelay: Infinity
    });

    for await (const line of rl) {
      if (!line.trim()) continue;

      const ip = line.split(' ')[0];
      if (excludedIPs.has(ip) || processedIPs.has(ip)) continue;
      processedIPs.add(ip);

      const reqMatch = line.match(/"(GET|POST|HEAD|OPTIONS|PUT|DELETE|TRACE|CONNECT|PATCH)\s([^ ]+)/);
      if (!reqMatch) continue;

      const method = reqMatch[1];
      const path = reqMatch[2];
      const uaMatch = line.match(/"[^"]*"\s*"([^"]*)"$/);
      const ua = uaMatch ? uaMatch[1] : '';
      const statusMatch = line.match(/"\s(\d{3})\s/);
      const statusCode = statusMatch ? parseInt(statusMatch[1]) : 200;

      const uaLower = ua.toLowerCase();

      if (trustedBots.some(b => uaLower.includes(b))) {
        stats.cleanBots++;
        continue;
      }

      const score = scoreRequest({ method, path, ua, raw: line, statusCode });

      if (score < 25) stats.humans++;
      else if (score < 80) stats.cleanBots++;
      else if (score < 140) stats.scanners++;
      else stats.exploits++;
    }

    res.json(stats);

  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

app.listen(PORT, '127.0.0.1', () => console.log(`Stats server on http://127.0.0.1:${PORT}`));
