<?php

use Slim\Http\Request;
use Slim\Http\Response;
use LINE\LINEBot;
use LINE\LINEBot\Constant\HTTPHeader;
use LINE\LINEBot\Event\MessageEvent;
use LINE\LINEBot\Event\MessageEvent\TextMessage;
use LINE\LINEBot\Exception\InvalidEventRequestException;
use LINE\LINEBot\Exception\InvalidSignatureException;

const SERVICE_URL = 'https://bang-tamin.herokuapp.com';

$app->get('/', function (Request $request, Response $response, array $args) {
    // $client = new GuzzleHttp\Client();
    // $res = $client->request('GET', SERVICE_URL."/products", [
    //     'auth' => ['user', 'pass']
    // ]);
    // $res->getBody();
    // Render index view
    $this->logger->info("Slim-Skeleton '/' route");
    return $this->renderer->render($response, 'index.phtml', $args);
});

$app->post('/', function (Request $req, Response $res, array $args) {
    $bot = $this->bot;
    $logger = $this->logger;

    $signature = $req->getHeader(HTTPHeader::LINE_SIGNATURE);
    if (empty($signature)) {
        return $res->withStatus(400, 'Bad Request');
    }

    try {
        $events = $bot->parseEventRequest($req->getBody(), $signature[0]);
    } catch (InvalidSignatureException $e) {
        return $res->withStatus(400, 'Invalid signature');
    } catch (InvalidEventRequestException $e) {
        return $res->withStatus(400, "Invalid event request");
    }

    foreach ($events as $event) {
        if (!($event instanceof MessageEvent)) {
            $logger->info('Non message event has come');
            continue;
        }

        if (!($event instanceof TextMessage)) {
            $logger->info('Non text message has come');
            continue;
        }

        $replyText = $event->getText();
        $resp = $bot->replyText($event->getReplyToken(), $replyText);
        $logger->info('Reply text: ' . $replyText);
        $logger->info($resp->getHTTPStatus() . ': ' . $resp->getRawBody());
    }

    $res->write('OK');
    return $res;
});