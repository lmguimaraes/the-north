const http = require("http");
const fs = require("fs");
const path = require("path");
const { spawn, spawnSync } = require("child_process");

const root = process.cwd();
const args = process.argv.slice(2);

function getArg(name, fallback) {
  const index = args.indexOf(name);
  if (index !== -1 && args[index + 1]) return args[index + 1];
  return fallback;
}

const host = getArg("--host", "127.0.0.1");
const port = Number(getArg("--port", process.env.PORT || "3000"));
const forceStatic = args.includes("--static");

if (!Number.isInteger(port) || port < 1 || port > 65535) {
  console.error("Invalid port. Use a value between 1 and 65535.");
  process.exit(1);
}

function hasPhpCli() {
  const result = spawnSync("php", ["-v"], {
    stdio: "ignore",
    windowsHide: true
  });

  return !result.error && result.status === 0;
}

function startPhpServer() {
  const address = `${host}:${port}`;
  console.log(`PHP detected. The North Latin Festival site is running at http://${address}`);
  console.log("The dynamic QR routes are available at /go/ and /go/admin/.");

  const php = spawn("php", ["-S", address, "-t", root], {
    cwd: root,
    stdio: "inherit",
    windowsHide: false
  });

  php.on("error", error => {
    console.error(`Could not start PHP: ${error.message}`);
    process.exit(1);
  });

  php.on("exit", code => {
    process.exit(code === null ? 0 : code);
  });

  const stop = signal => {
    if (!php.killed) php.kill(signal);
  };

  process.on("SIGINT", () => stop("SIGINT"));
  process.on("SIGTERM", () => stop("SIGTERM"));
}

const mimeTypes = {
  ".html": "text/html; charset=utf-8",
  ".css": "text/css; charset=utf-8",
  ".js": "application/javascript; charset=utf-8",
  ".json": "application/json; charset=utf-8",
  ".txt": "text/plain; charset=utf-8",
  ".pdf": "application/pdf",
  ".jpg": "image/jpeg",
  ".jpeg": "image/jpeg",
  ".png": "image/png",
  ".webp": "image/webp",
  ".svg": "image/svg+xml",
  ".ico": "image/x-icon",
  ".wav": "audio/wav",
  ".mp3": "audio/mpeg",
  ".mp4": "video/mp4"
};

function parseRequestPath(requestUrl) {
  try {
    const parsed = new URL(requestUrl, `http://${host}:${port}`);
    return decodeURIComponent(parsed.pathname);
  } catch {
    return null;
  }
}

function safeFilePath(pathname) {
  const requested = pathname === "/" ? "/index.html" : pathname;
  const resolved = path.resolve(root, `.${requested}`);
  const relative = path.relative(root, resolved);

  if (relative.startsWith("..") || path.isAbsolute(relative)) return null;
  return resolved;
}

function isSensitivePath(filePath) {
  const relative = path.relative(root, filePath);
  const segments = relative.split(path.sep);
  const extension = path.extname(filePath).toLowerCase();

  if (extension === ".php" || extension === ".htaccess") return true;
  if (segments.some(segment => segment.startsWith("."))) return true;
  return segments.length >= 2 && segments[0] === "go" && segments[1] === "data";
}

function sendPhpRequired(res) {
  const body = `<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>PHP required</title>
  <style>
    body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #f4f7f8; color: #263d46; font: 16px/1.55 system-ui, sans-serif; }
    main { width: min(680px, calc(100% - 40px)); padding: 32px; border: 1px solid #d7e2e6; border-radius: 18px; background: white; box-shadow: 0 18px 55px rgba(38,61,70,.12); }
    h1 { margin-top: 0; }
    code { padding: .15rem .4rem; border-radius: .35rem; background: #edf3f5; }
  </style>
</head>
<body>
  <main>
    <h1>PHP is required for the dynamic QR routes</h1>
    <p>The regular site can be previewed by the Node static server, but <code>/go/</code> and <code>/go/admin/</code> are PHP applications.</p>
    <p>Install PHP 7.4 or newer, make sure <code>php -v</code> works in this terminal, stop this server, and run <code>npm run dev</code> again.</p>
  </main>
</body>
</html>`;

  res.writeHead(503, {
    "Content-Type": "text/html; charset=utf-8",
    "Cache-Control": "no-store",
    "X-Content-Type-Options": "nosniff"
  });
  res.end(body);
}

function sendFile(req, res, filePath) {
  if (isSensitivePath(filePath)) {
    res.writeHead(403, { "Content-Type": "text/plain; charset=utf-8" });
    res.end("Forbidden");
    return;
  }

  fs.readFile(filePath, (error, content) => {
    if (error) {
      res.writeHead(error.code === "ENOENT" ? 404 : 500, {
        "Content-Type": "text/plain; charset=utf-8"
      });
      res.end(error.code === "ENOENT" ? "Not found" : "Server error");
      return;
    }

    const extension = path.extname(filePath).toLowerCase();
    const headers = {
      "Content-Type": mimeTypes[extension] || "application/octet-stream",
      "X-Content-Type-Options": "nosniff"
    };

    res.writeHead(200, headers);
    if (req.method === "HEAD") {
      res.end();
      return;
    }
    res.end(content);
  });
}

function startStaticServer() {
  console.warn("PHP was not found in PATH. Starting the static preview only.");
  console.warn("The /go/ and /go/admin/ routes require PHP 7.4 or newer.");

  const server = http.createServer((req, res) => {
    if (req.method !== "GET" && req.method !== "HEAD") {
      res.writeHead(405, { Allow: "GET, HEAD", "Content-Type": "text/plain; charset=utf-8" });
      res.end("Method not allowed");
      return;
    }

    const pathname = parseRequestPath(req.url || "/");
    if (pathname === null) {
      res.writeHead(400, { "Content-Type": "text/plain; charset=utf-8" });
      res.end("Bad request");
      return;
    }

    if (/^\/go(?:\/|$)/.test(pathname)) {
      sendPhpRequired(res);
      return;
    }

    const filePath = safeFilePath(pathname);
    if (!filePath) {
      res.writeHead(403, { "Content-Type": "text/plain; charset=utf-8" });
      res.end("Forbidden");
      return;
    }

    fs.stat(filePath, (statError, stat) => {
      if (!statError && stat.isDirectory()) {
        sendFile(req, res, path.join(filePath, "index.html"));
        return;
      }

      if (statError && statError.code !== "ENOENT") {
        res.writeHead(500, { "Content-Type": "text/plain; charset=utf-8" });
        res.end("Server error");
        return;
      }

      sendFile(req, res, filePath);
    });
  });

  server.listen(port, host, () => {
    console.log(`The North Latin Festival static preview is running at http://${host}:${port}`);
    if (host === "0.0.0.0") {
      console.log("Use your computer's local network IP address to share the preview on the same network.");
    }
  });
}

if (!forceStatic && hasPhpCli()) {
  startPhpServer();
} else {
  startStaticServer();
}
