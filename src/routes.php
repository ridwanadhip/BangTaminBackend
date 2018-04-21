<?php

use Slim\Http\Request;
use Slim\Http\Response;
use LINE\LINEBot;
use LINE\LINEBot\MessageBuilder\TemplateBuilder\CarouselTemplateBuilder;
use LINE\LINEBot\MessageBuilder\TemplateBuilder\ImageCarouselColumnTemplateBuilder;
use LINE\LINEBot\MessageBuilder\TemplateBuilder\CarouselColumnTemplateBuilder;
use LINE\LINEBot\MessageBuilder\TemplateBuilder\ImageCarouselTemplateBuilder;
use LINE\LINEBot\MessageBuilder\TemplateBuilder\ButtonTemplateBuilder;
use LINE\LINEBot\TemplateActionBuilder\MessageTemplateActionBuilder;
use LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder;
use LINE\LINEBot\TemplateActionBuilder\UriTemplateActionBuilder;
use LINE\LINEBot\TemplateActionBuilder\DatetimePickerTemplateActionBuilder;
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

const SERVICE_URL = 'https://bang-tamin.herokuapp.com';

$app->get('/', function (Request $request, Response $response, array $args) {
    $client = new GuzzleHttp\Client();

    // $result = $client->request('GET', SERVICE_URL.'/products', [
    //     'auth' => ['user', 'pass']
    // ]);
    // $decodedResults = json_decode($result->getBody()->getContents(), true);

    // foreach ($decodedResults as $item) {
    //     $this->logger->info($item['image']);
    // }
    
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
                // // get request from backend
                // $client = new GuzzleHttp\Client();
                // $result = $client->request('GET', SERVICE_URL.'/products', ['auth' => ['user', 'pass']]);
                // $decodedResults = json_decode($result->getBody()->getContents(), true);

                // // Error log to heroku
                // error_log($event->getText());
                // error_log($event->getUserId());

                // // Example of text
                // $replyText = $event->getText();
                // $response = $bot->replyText($event->getReplyToken(), $replyText);

                // // Example of carousel
                // $products = [];
                // foreach ($decodedResults as $item) {
                //     $httpsImage = str_replace('http://', 'https://', $item['image']);
                //     array_push(
                //         $products, 
                //         new CarouselColumnTemplateBuilder(
                //             $item['name'],
                //             $item['desc'],
                //             $httpsImage, 
                //             [
                //                 new UriTemplateActionBuilder("link", $httpsImage)
                //             ]
                //         )
                //     );
                // }
                // $response = $bot->replyMessage(
                //     $event->getReplyToken(), 
                //     new TemplateMessageBuilder(
                //         'alt test', 
                //         new CarouselTemplateBuilder($products)
                //     )
                // );

                // // Example of button
                // $response = $bot->replyMessage(
                //     $event->getReplyToken(),
                //     new TemplateMessageBuilder(
                //         'alt test',
                //         new ButtonTemplateBuilder(
                //             null,
                //             'button button',
                //             null,
                //             [
                //                 new PostbackTemplateActionBuilder('postback label', 'post=back'),
                //                 new MessageTemplateActionBuilder('message label', 'test message'),
                //                 new UriTemplateActionBuilder('uri label', 'https://example.com'),
                //             ]
                //         )
                //     )
                // );

                $userId = $event->getUserId();
                $client = new GuzzleHttp\Client();

                $stateJson = $client->request('GET', SERVICE_URL.'/bot-states?userId='.$userId, ['auth' => ['user', 'pass']]);
                $state = json_decode($result->getBody()->getContents(), true);

                if (count($state) == 0) {
                    $createStateResponse = $client->request('POST', SERVICE_URL.'/bot-states', [
                        GuzzleHttp\RequestOptions::JSON => [
                            'userId' => $userId,
                            'state' => '0',
                        ],
                    ]);
                }
            }
        }

        continue;
    }

    $res->write('OK');
    return $res;
});