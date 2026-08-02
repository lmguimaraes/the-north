const http = require("http");
const fs = require("fs");
const path = require("path");

const root = process.cwd();
const args = process.argv.slice(2);

function getArg(name, fallback) {
  const index = args.indexOf(name);
  if (index !== -1 && args[index + 1]) return args[index + 1];
  return fallback;
}

const host = getArg("--host", "127.0.0.1");
const port = Number(getArg("--port", process.env.PORT || "3000"));

const mimeTypes = {
  ".html": "text/html; charset=utf-8",
  ".css": "text/css; charset=utf-8",
  ".js": "application/javascript; charset=utf-8",
  ".json": "application/json; charset=utf-8",
  ".jpg": "image/jpeg",
  ".jpeg": "image/jpeg",
  ".png": "image/png",
  ".webp": "image/webp",
  ".svg": "image/svg+xml",
  ".ico": "image/x-icon"
};

function safeFilePath(requestUrl) {
  const parsed = new URL(requestUrl, `http://${host}:${port}`);
  let pathname = decodeURIComponent(parsed.pathname);

  if (pathname === "/") pathname = "/index.html";

  const resolved = path.normalize(path.join(root, pathname));
  if (!resolved.startsWith(root)) return null;

  return resolved;
}

const server = http.createServer((req, res) => {
  const filePath = safeFilePath(req.url || "/");

  if (!filePath) {
    res.writeHead(403);
    res.end("Forbidden");
    return;
  }

  fs.readFile(filePath, (error, content) => {
    if (error) {
      res.writeHead(error.code === "ENOENT" ? 404 : 500, { "Content-Type": "text/plain; charset=utf-8" });
      res.end(error.code === "ENOENT" ? "Not found" : "Server error");
      return;
    }

    const ext = path.extname(filePath).toLowerCase();
    res.writeHead(200, { "Content-Type": mimeTypes[ext] || "application/octet-stream" });
    res.end(content);
  });
});

server.listen(port, host, () => {
  console.log(`The North Latin Festival site is running at http://${host}:${port}`);
  if (host === "0.0.0.0") {
    console.log("Use your computer's local network IP address to share the preview with people on the same Wi-Fi.");
  }
});
