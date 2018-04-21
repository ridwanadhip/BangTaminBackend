<?php

use Slim\Http\Request;
use Slim\Http\Response;
use LINE\LINEBot;
use LINE\LINEBot\MessageBuilder\TemplateBuilder\CarouselTemplateBuilder;
use LINE\LINEBot\MessageBuilder\TemplateBuilder\ImageCarouselTemplateBuilder;
use LINE\LINEBot\TemplateActionBuilder\UriTemplateActionBuilder;
use LINE\LINEBot\MessageBuilder\TemplateMessageBuilder;
use LINE\LINEBot\Constant\HTTPHeader;
use LINE\LINEBot\Event\BeaconDetectionEvent;
use LINE\LINEBot\Event\FollowEvent;
use LINE\LINEBot\Event\JoinEvent;
use LINE\LINEBot\Event\LeaveEvent;
use LINE\LINEBot\Event\MessageEvent;
use LINE\LINEBot\Event\MessageEvent\AudioMessage;
use LINE\LINEBot\Event\MessageEvent\ImageMessage;
use LINE\LINEBot\Event\MessageEvent\LocationMessage;
use LINE\LINEBot\Event\MessageEvent\StickerMessage;
use LINE\LINEBot\Event\MessageEvent\TextMessage;
use LINE\LINEBot\Event\MessageEvent\UnknownMessage;
use LINE\LINEBot\Event\MessageEvent\VideoMessage;
use LINE\LINEBot\Event\PostbackEvent;
use LINE\LINEBot\Event\UnfollowEvent;
use LINE\LINEBot\Event\UnknownEvent;
use LINE\LINEBot\Exception\InvalidEventRequestException;
use LINE\LINEBot\Exception\InvalidSignatureException;
use function GuzzleHttp\json_decode;
use LINE\LINEBot\MessageBuilder\TemplateBuilder\ImageCarouselColumnTemplateBuilder;

const SERVICE_URL = 'https://bang-tamin.herokuapp.com';

$app->get('/', function (Request $request, Response $response, array $args) {
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
        return $res->withStatus(400, 'Invalid event request');
    }

    foreach ($events as $event) {
        if ($event instanceof MessageEvent) {
            if ($event instanceof TextMessage) {
                $client = new GuzzleHttp\Client();
                $result = $client->request('GET', SERVICE_URL.'/products', [
                    'auth' => ['user', 'pass']
                ]);

                $decodedResults = json_decode($result->getBody()->getContents());
                $products = [];

                foreach ($decodedResults as $item) {
                    array_push(
                        $products, 
                        new ImageCarouselColumnTemplateBuilder(
                            $item['image'], 
                            new UriTemplateActionBuilder("test", $item['image'])
                        )
                    );
                }

                // $response = $bot->replyText($event->getReplyToken(), $replyText);
                $response = $bot->replyMessage(
                    $event->getReplyToken(), 
                    new TemplateMessageBuilder(
                        'test', 
                        new ImageCarouselTemplateBuilder($products)
                    )
                );
            }
        }
        continue;
    }

    $res->write('OK');
    return $res;
});