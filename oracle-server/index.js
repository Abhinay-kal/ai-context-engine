import { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { SSEServerTransport } from "@modelcontextprotocol/sdk/server/sse.js";
import express from "express";
import cors from "cors";
import { z } from "zod";

// Initialize the MCP Server
const server = new McpServer({
  name: "ContextPress-Oracle-Relay",
  version: "1.0.0"
});

/**
 * Register the 'get_wordpress_context' tool.
 */
server.tool(
  "get_wordpress_context",
  {
    target_url: z.string().describe("The base URL of the WordPress site (e.g., https://example.com)"),
    username: z.string().describe("The WordPress administrator username (used with an Application Password)."),
    app_password: z.string().describe("The WordPress Application Password generated in the plugin's Setup tab."),
    plugins: z.array(z.string()).describe("An array of plugin slugs to extract context for (e.g., ['woocommerce', 'elementor'])")
  },
  async ({ target_url, username, app_password, plugins }) => {
    try {
      const baseUrl = target_url.replace(/\/$/, "");
      const endpoint = `${baseUrl}/wp-json/wp-ai-context/v1/generate`;

      const response = await fetch(endpoint, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "Authorization": "Basic " + Buffer.from(`${username}:${app_password}`).toString("base64")
        },
        body: JSON.stringify({ plugins, mode: "delta" })
      });

      if (!response.ok) {
        if (response.status === 401 || response.status === 403) {
          return { content: [{ type: "text", text: "Error: Unauthorized. Check your Application Password credentials." }] };
        }
        return { content: [{ type: "text", text: `Error: HTTP ${response.status} from WordPress.` }] };
      }

      const data = await response.json();

      return {
        content: [{ type: "text", text: data.markdown || JSON.stringify(data, null, 2) }]
      };
    } catch (error) {
      return { content: [{ type: "text", text: `Connection Error: ${error.message}` }] };
    }
  }
);

// Express setup for Oracle Cloud
const app = express();
app.use(cors()); // Crucial for remote MCP clients

let transport;

// The SSE endpoint Claude connects to initially
app.get("/sse", async (req, res) => {
  transport = new SSEServerTransport("/message", res);
  await server.connect(transport);
  
  // Cleanup when Claude disconnects
  req.on('close', () => {
    console.log("Client disconnected");
    transport = null;
  });
});

// The message endpoint Claude POSTs tool requests to
app.post("/message", express.json(), async (req, res) => {
  if (!transport) {
    return res.status(503).json({ error: "MCP server not connected via SSE yet." });
  }
  await transport.handlePostMessage(req, res);
});

const PORT = process.env.PORT || 3000;
app.listen(PORT, () => {
  console.log(`ContextPress Oracle MCP Server running on port ${PORT}`);
  console.log(`SSE endpoint available at: http://<your-oracle-ip>:${PORT}/sse`);
});
