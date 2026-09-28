<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <title>FlowTree API — Swagger</title>
  <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css">
</head>
<body>
  <div id="swagger-ui"></div>
  <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js" crossorigin></script>
  <script>
    window.onload = () => {
      window.ui = SwaggerUIBundle({ url: '/docs/api.yaml', dom_id: '#swagger-ui', persistAuthorization: true })
    }
  </script>
</body>
</html>
