const http = require("http");
const { WebSocketServer } = require("ws");

const port = Number(process.env.WS_PORT || 8080);
const host = process.env.WS_HOST || "0.0.0.0";

const server = http.createServer((req, res) => {
  if (req.method === "GET" && req.url === "/health") {
    res.writeHead(200, { "Content-Type": "application/json" });
    res.end(JSON.stringify({ ok: true, clients: wss.clients.size }));
    return;
  }

  if (req.method === "POST" && req.url === "/publish") {
    let body = "";
    req.on("data", (chunk) => {
      body += chunk;
      if (body.length > 1024 * 128) {
        req.destroy();
      }
    });

    req.on("end", () => {
      let payload = { event: "notification:refresh", ts: Date.now() };
      try {
        if (body.trim() !== "") {
          const parsed = JSON.parse(body);
          if (parsed && typeof parsed === "object") {
            payload = { ts: Date.now(), ...parsed };
          }
        }
      } catch (err) {
        // Fallback to generic refresh event.
      }

      broadcast(payload);
      res.writeHead(200, { "Content-Type": "application/json" });
      res.end(JSON.stringify({ ok: true, clients: wss.clients.size }));
    });
    return;
  }

  res.writeHead(404, { "Content-Type": "application/json" });
  res.end(JSON.stringify({ ok: false, message: "Not found" }));
});

const wss = new WebSocketServer({ noServer: true });

function broadcast(payload) {
  const message = JSON.stringify(payload);
  for (const client of wss.clients) {
    if (client.readyState === 1) {
      client.send(message);
    }
  }
}

wss.on("connection", (socket) => {
  socket.send(JSON.stringify({ event: "socket:connected", ts: Date.now() }));
});

server.on("upgrade", (req, socket, head) => {
  if (req.url !== "/notifications") {
    socket.destroy();
    return;
  }

  wss.handleUpgrade(req, socket, head, (ws) => {
    wss.emit("connection", ws, req);
  });
});

server.listen(port, host, () => {
  // eslint-disable-next-line no-console
  console.log(`[ws-server] listening on ws://${host}:${port}/notifications`);
});
