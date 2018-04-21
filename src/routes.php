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
use function Monolog\Handler\error_log;

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

                // // Example of multiline
                // $reply = "Selamat datang\n" . 
                //     "Pilih salah satu menu berikut:\n";
                // $response = $bot->replyText($event->getReplyToken(), $reply);

                $userId = $event->getUserId();
                $client = new GuzzleHttp\Client();
                $stateJson = $client->request('GET', SERVICE_URL.'/bot-states?userId='.$userId, ['auth' => ['user', 'pass']]);
                // TODO: handle error
                $state = json_decode($stateJson->getBody()->getContents(), true);

                $stateCode = '0';
                if (count($state) > 0) {
                    $stateCode = $state[0]['state'];
                } else {
                    $createJson = $client->request('POST', SERVICE_URL.'/bot-states', [
                        GuzzleHttp\RequestOptions::JSON => [
                            'userId' => $userId,
                            'state' => '0',
                        ],
                    ]);

                    $stateJson = $client->request('GET', SERVICE_URL.'/bot-states?userId='.$userId, ['auth' => ['user', 'pass']]);
                    $state = json_decode($stateJson->getBody()->getContents(), true);

                    // TODO: handle error
                }
                
                if ($stateCode == '0' || $stateCode == '1') {
                    if ($stateCode == '0') {
                        $changeJson = $client->request('PUT', SERVICE_URL.'/bot-states', [
                            GuzzleHttp\RequestOptions::JSON => [
                                'id' => $state[0]['id'],
                                'state' => '1',
                            ],
                        ]);
                    }

                    $response = $bot->replyMessage(
                        $event->getReplyToken(), 
                        new TemplateMessageBuilder(
                            'alt test', 
                            new CarouselTemplateBuilder([
                                new CarouselColumnTemplateBuilder(
                                    null,
                                    'Info SPBU',
                                    'https://www.example.com/test.jpg', 
                                    [
                                        new PostbackTemplateActionBuilder('Detail', 'post=1'),
                                    ]
                                ),
                                new CarouselColumnTemplateBuilder(
                                    null,
                                    'Shop',
                                    'https://www.example.com/test.jpg', 
                                    [
                                        new PostbackTemplateActionBuilder('Detail', 'post=2'),
                                    ]
                                ),
                                new CarouselColumnTemplateBuilder(
                                    null,
                                    'Promo',
                                    'https://www.example.com/test.jpg', 
                                    [
                                        new PostbackTemplateActionBuilder('Detail', 'post=3'),
                                    ]
                                ),
                                new CarouselColumnTemplateBuilder(
                                    null,
                                    'My Account',
                                    'https://www.example.com/test.jpg', 
                                    [
                                        new PostbackTemplateActionBuilder('Detail', 'post=4'),
                                    ]
                                ),
                                new CarouselColumnTemplateBuilder(
                                    null,
                                    'Costumer Account',
                                    'https://www.example.com/test.jpg', 
                                    [
                                        new PostbackTemplateActionBuilder('Detail', 'post=5'),
                                    ]
                                ),
                            ])
                        )
                    );

                    continue;
                } else if ($stateCode == '2') {

                }
            }
        } else if ($event instanceof PostbackEvent) {
            if ($stateCode == '1') {
                $changeJson = $client->request('PUT', SERVICE_URL.'/bot-states', [
                    GuzzleHttp\RequestOptions::JSON => [
                        'id' => $state[0]['id'],
                        'state' => '2',
                    ],
                ]);

                error_log($event->getPostbackData());
            } else if ($stateCode == '2') {

            }
        }

        continue;
    }

    $res->write('OK');
    return $res;
});