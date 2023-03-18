 <?php
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Slim\Exception\HttpNotFoundException;

require __DIR__ . '/vendor/autoload.php';

$app = AppFactory::create();



$db = new PDO('sqlite:database.sqlite');

$secretKey = "g?Zp@sDxvE{W7m8tXyNBLFJ#K*H!V5jCfAQo&b_u-2^h+rT]z;0nI6lO1iS";

 
function getUserIdFromToken($token, $secretKey) {
    try {
        $decoded = JWT::decode($token,  new Key($secretKey, 'HS256') );
        return $decoded->user_id;
    } catch (Exception $e) {
        echo $e;
        return null;
    }
}

$app->post('/users', function (Request $request, Response $response, $args) use ($db) {
    $input = $request->getParsedBody();
    $username = $input['username'];
    $password = $input['password'];

    $stmt = $db->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
    $stmt->execute([$username, $password]);
    $newId = $db->lastInsertId();
    $data = ['id' => $newId];

    $json = json_encode($data, JSON_PRETTY_PRINT);
    $response->getBody()->write($json);
    return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(201);
});

$app->post('/login', function ($request, $response, $args) use ($db, $secretKey) {
    $input = $request->getParsedBody();
    $username = $input['username'];
    $password = $input['password'];
    $user = $db->query("SELECT * FROM users WHERE username = '$username' AND password = '$password'")->fetchObject();
    if ($user) {
        $payload = [
            'user_id' => $user->id
        ];
        $token = JWT::encode($payload, $secretKey, 'HS256');
        $data = ['token' => $token];
        $json = json_encode($data, JSON_PRETTY_PRINT);
        $response->getBody()->write($json);
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    } else {
        return $response->withStatus(401);
    }
});

$app->post('/messages', function ($request, $response, $args) use ($db, $secretKey) {
    $input = $request->getParsedBody();
    $authorizationHeader = $request->getHeader('Authorization');
    $token = str_replace('Bearer ', '', $authorizationHeader[0]);
    $recipient_id = $input['recipient_id'];
    $message = $input['message'];
    $user_id = getUserIdFromToken($token, $secretKey);
    $stmt = $db->prepare("INSERT INTO messages (sender_id, recipient_id, message, created_at) VALUES (?, ?, ?, datetime('now'))");
    $stmt->execute([$user_id, $recipient_id, $message]);
    $newId = $db->lastInsertId();

    $data = ['id' => $newId];
    $json = json_encode($data, JSON_PRETTY_PRINT);
    $response->getBody()->write($json);
    return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(201);
    
});

$app->get('/messages', function ($request, $response, $args) use ($db, $secretKey) {
    $authorizationHeader = $request->getHeader('Authorization');
    $token = str_replace('Bearer ', '', $authorizationHeader[0]);
    $input = $request->getParsedBody();
    $user_id = getUserIdFromToken($token, $secretKey);

    $messages = $db->query("SELECT m.*, u.username as sender_username FROM messages m JOIN users u ON m.sender_id = u.id WHERE m.recipient_id = $user_id ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

    $json = json_encode($messages, JSON_PRETTY_PRINT);
    $response->getBody()->write($json);
    return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(200);
});

$app->run();


