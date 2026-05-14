<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>BidStream Realtime Test</title>
    @vite(['resources/js/app.js'])
</head>
<body style="font-family: Arial, sans-serif; padding: 40px;">
    <h1>BidStream - Teste em Tempo Real</h1>

    <p>Ouvindo canal:</p>

    <pre>auction.1</pre>

    <h2>Último lance recebido:</h2>

    <pre id="last-bid">Aguardando lance...</pre>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            window.Echo.channel('auction.1')
                .listen('.bid.placed', (event) => {
                    console.log('Evento recebido:', event);

                    document.getElementById('last-bid').textContent = JSON.stringify(event, null, 2);
                });
        });
    </script>
</body>
</html>