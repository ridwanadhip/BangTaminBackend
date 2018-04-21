<?php

use Slim\Http\Request;
use Slim\Http\Response;
use LINE\LINEBot;
use LINE\LINEBot\MessageBuilder\TemplateBuilder\CarouselTemplateBuilder;
use LINE\LINEBot\MessageBuilder\TemplateBuilder\ImageCarouselColumnTemplateBuilder;
use LINE\LINEBot\MessageBuilder\TemplateBuilder\CarouselColumnTemplateBuilder;
use LINE\LINEBot\MessageBuilder\TemplateBuilder\ImageCarouselTemplateBuilder;
use LINE\LINEBot\MessageBuilder\TemplateBuilder\ButtonTemplateBuilder;
use LINE\LINEBot\MessageBuilder\MultiMessageBuilder;
use LINE\LINEBot\MessageBuilder\TextMessageBuilder;
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
        $userId = $event->getUserId();
        $client = new GuzzleHttp\Client();
        $stateJson = $client->request('GET', SERVICE_URL.'/bot-states?userId='.$userId, ['auth' => ['user', 'pass']]);
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
        }

        if ($event instanceof MessageEvent) {
            if ($event instanceof TextMessage) {
                if ($stateCode == '0') {
                    $changeJson = $client->request('PUT', SERVICE_URL.'/bot-states', [
                        GuzzleHttp\RequestOptions::JSON => [
                            'id' => $state[0]['id'],
                            'state' => '2',
                        ],
                    ]);

                    $multi = new MultiMessageBuilder();
                    $multi
                        ->add(new TextMessageBuilder('Apa yang bisa abang bantu?'))
                        ->add(newHomeCarousel());
                    $response = $bot->replyMessage($event->getReplyToken(), $multi);
                } else if ($stateCode == '1') {
                    $changeJson = $client->request('PUT', SERVICE_URL.'/bot-states', [
                        GuzzleHttp\RequestOptions::JSON => [
                            'id' => $state[0]['id'],
                            'state' => '2',
                        ],
                    ]);

                    $multi = new MultiMessageBuilder();
                    $multi
                        ->add(new TextMessageBuilder('Apa yang bisa abang bantu?'))
                        ->add(newHomeCarousel());
                    $response = $bot->replyMessage($event->getReplyToken(), $multi);
                }
            }
        } else if ($event instanceof PostbackEvent) {
            if ($stateCode == '2') {
                $value = $event->getPostbackData();

                if ($value == '1') {
                    $replyText = "menu 1";
                    $response = $bot->replyText($event->getReplyToken(), $replyText);
                } else if ($value == '2') {
                    $replyText = "menu 2";
                    $response = $bot->replyText($event->getReplyToken(), $replyText);
                } else if ($value == '3') {
                    $changeJson = $client->request('PUT', SERVICE_URL.'/bot-states', [
                        GuzzleHttp\RequestOptions::JSON => [
                            'id' => $state[0]['id'],
                            'state' => '8',
                        ],
                    ]);

                    $promotionsJson = $client->request('GET', SERVICE_URL.'/promotions', ['auth' => ['user', 'pass']]);
                    $decodedResults = json_decode($promotionsJson->getBody()->getContents(), true);
                    error_log($decodedResults);

                    $promotions = [];
                    foreach ($decodedResults as $item) {
                        array_push(
                            $promotions, 
                            new CarouselColumnTemplateBuilder(
                                $item['title'],
                                $item['desc'],
                                $item['image'], 
                                [
                                    new PostbackTemplateActionBuilder('Detail', $item['id']),
                                ]
                            )
                        );
                    }

                    $response = $bot->replyMessage(
                        $event->getReplyToken(), 
                        new TemplateMessageBuilder(
                            'carousel promo', 
                            new CarouselTemplateBuilder($promotions)
                        )
                    );
                } else if ($value == '4') {
                    $replyText = "menu 4";
                    $response = $bot->replyText($event->getReplyToken(), $replyText);
                } else if ($value == '5') {
                    $replyText = "menu 5";
                    $response = $bot->replyText($event->getReplyToken(), $replyText);
                }
            }
        }
    }

    $res->write('OK');
    return $res;
});

function newHomeCarousel() {
    return new TemplateMessageBuilder(
        'alt test', 
        new CarouselTemplateBuilder([
            new CarouselColumnTemplateBuilder(
                null,
                'Info SPBU',
                'https://res.cloudinary.com/indonesia-gw/image/upload/v1524316653/station.png', 
                [
                    new PostbackTemplateActionBuilder('Detail', '1'),
                ]
            ),
            new CarouselColumnTemplateBuilder(
                null,
                'Shop',
                'https://res.cloudinary.com/indonesia-gw/image/upload/v1524316643/shop.png', 
                [
                    new PostbackTemplateActionBuilder('Detail', '2'),
                ]
            ),
            new CarouselColumnTemplateBuilder(
                null,
                'Promo',
                'https://res.cloudinary.com/indonesia-gw/image/upload/v1524316632/promo.png', 
                [
                    new PostbackTemplateActionBuilder('Detail', '3'),
                ]
            ),
            new CarouselColumnTemplateBuilder(
                null,
                'My Account',
                'https://res.cloudinary.com/indonesia-gw/image/upload/v1524316569/account.png', 
                [
                    new PostbackTemplateActionBuilder('Detail', '4'),
                ]
            ),
            new CarouselColumnTemplateBuilder(
                null,
                'Customer Service',
                'https://res.cloudinary.com/indonesia-gw/image/upload/v1524316585/cust_service.png', 
                [
                    new PostbackTemplateActionBuilder('Detail', '5'),
                ]
            ),
        ])
    );
}