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
use LINE\LINEBot\MessageBuilder\ImageMessageBuilder;

const SERVICE_URL = 'https://bang-tamin.herokuapp.com';

$app->get('/', function (Request $request, Response $response, array $args) {
    $client = new GuzzleHttp\Client();
    return $this->renderer->render($response, 'index.phtml', $args);
});

$app->post('/', function (Request $req, Response $res, array $args) {
    $bot = $this->bot;
    $logger = $this->logger;
    $client = new GuzzleHttp\Client();

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
        $stateJson = $client->request('GET', SERVICE_URL.'/bot-states?userId='.$userId, ['auth' => ['user', 'pass']]);
        $state = json_decode($stateJson->getBody()->getContents(), true);

        $stateCode = 'initial';
        if (count($state) > 0) {
            $stateCode = $state[0]['state'];
        } else {
            $createJson = $client->request('POST', SERVICE_URL.'/bot-states', [
                GuzzleHttp\RequestOptions::JSON => [
                    'userId' => $userId,
                    'state' => 'initial',
                ],
            ]);

            $stateJson = $client->request('GET', SERVICE_URL.'/bot-states?userId='.$userId, ['auth' => ['user', 'pass']]);
            $state = json_decode($stateJson->getBody()->getContents(), true);
        }

        if ($event instanceof MessageEvent) {
            if ($event instanceof TextMessage) {
                $replyText = $event->getText();

                if ($replyText == '/reset') {
                    $changeJson = $client->request('PUT', SERVICE_URL.'/bot-states', [
                        GuzzleHttp\RequestOptions::JSON => [
                            'id' => $state[0]['id'],
                            'state' => 'initial',
                        ],
                    ]);

                    continue;
                }

                if ($stateCode == 'initial') {
                    $changeJson = $client->request('PUT', SERVICE_URL.'/bot-states', [
                        GuzzleHttp\RequestOptions::JSON => [
                            'id' => $state[0]['id'],
                            'state' => 'mainMenu',
                        ],
                    ]);

                    $multi = new MultiMessageBuilder();
                    $multi
                        ->add(new TextMessageBuilder('Haii Bang Tamin siap bantu kamu!. ' . 
                        'Abang akan bawa kamu banyak keuntungan dari produk Pertamina. ' . 
                        'Silakan pilih menu di bawah ini untuk melanjutkan.'))
                        ->add(newHomeCarousel());
                    $response = $bot->replyMessage($event->getReplyToken(), $multi);
                } else if ($stateCode == 'secondTime') {
                    if (strtolower($replyText) == 'hai') {
                        $changeJson = $client->request('PUT', SERVICE_URL.'/bot-states', [
                            GuzzleHttp\RequestOptions::JSON => [
                                'id' => $state[0]['id'],
                                'state' => 'mainMenu',
                            ],
                        ]);
    
                        $multi = new MultiMessageBuilder();
                        $multi
                            ->add(new TextMessageBuilder('Apa yang bisa abang bantu?'))
                            ->add(newHomeCarousel());
                        $response = $bot->replyMessage($event->getReplyToken(), $multi);
                    } else {
                        $multi = new MultiMessageBuilder();
                        $multi
                            ->add(new TextMessageBuilder('Kalo ada yang perlu abang bantu, say ‘hai’ aja yaa :D'));
                        $response = $bot->replyMessage($event->getReplyToken(), $multi);
                    }
                    
                } else if ($stateCode == 'askSpbuLocation') {
                    $changeJson = $client->request('PUT', SERVICE_URL.'/bot-states', [
                        GuzzleHttp\RequestOptions::JSON => [
                            'id' => $state[0]['id'],
                            'state' => 'promptYesNo',
                        ],
                    ]);

                    $stationsJson = $client->request('GET', SERVICE_URL.'/stations', ['auth' => ['user', 'pass']]);
                    $decodedResults = json_decode($stationsJson->getBody()->getContents(), true);
                    $stations = [];
                    foreach ($decodedResults as $item) {
                        array_push(
                            $stations, 
                            new CarouselColumnTemplateBuilder(
                                substr($item['name'], 0, 40),
                                substr($item['location'], 0, 60),
                                null, 
                                [
                                    new UriTemplateActionBuilder('Lokasi', $item['link']),
                                ]
                            )
                        );
                    }

                    // $multi = new MultiMessageBuilder();
                    // $multi
                    //     ->add(new TextMessageBuilder('Okee, Bang Tamin punya promo nih buat kamu!'))
                    //     ->add(new TemplateMessageBuilder('select promo', new CarouselTemplateBuilder($promotions)))
                    //     ->add(newDecisionButtons());
                    // $response = $bot->replyMessage($event->getReplyToken(), $multi);

                    $multi = new MultiMessageBuilder();
                    $multi
                        ->add(new TemplateMessageBuilder('select station', new CarouselTemplateBuilder($stations)))
                        ->add(newDecisionButtons());
                    $response = $bot->replyMessage($event->getReplyToken(), $multi);
                } else if ($stateCode == 'askServiceDescription') {
                    $changeJson = $client->request('PUT', SERVICE_URL.'/bot-states', [
                        GuzzleHttp\RequestOptions::JSON => [
                            'id' => $state[0]['id'],
                            'state' => 'askServiceWhere',
                        ],
                    ]);

                    $createJson = $client->request('POST', SERVICE_URL.'/bot-state-data', [
                        GuzzleHttp\RequestOptions::JSON => [
                            'userId' => $userId,
                            'state' => 'askServiceDescription',
                            'value' => $replyText,
                        ],
                    ]);

                    $multi = new MultiMessageBuilder();
                    $multi
                        ->add(new TextMessageBuilder('Dimana SPBU tempat kejadian yang dimaksud?'));
                    $response = $bot->replyMessage($event->getReplyToken(), $multi);
                } else if ($stateCode == 'askServiceWhere') {
                    $changeJson = $client->request('PUT', SERVICE_URL.'/bot-states', [
                        GuzzleHttp\RequestOptions::JSON => [
                            'id' => $state[0]['id'],
                            'state' => 'askServiceWhen',
                        ],
                    ]);

                    $createJson = $client->request('POST', SERVICE_URL.'/bot-state-data', [
                        GuzzleHttp\RequestOptions::JSON => [
                            'userId' => $userId,
                            'state' => 'askServiceWhere',
                            'value' => $replyText,
                        ],
                    ]);

                    $multi = new MultiMessageBuilder();
                    $multi
                        ->add(new TextMessageBuilder('Kapan kamu mengalami kejadian tersebut?'));
                    $response = $bot->replyMessage($event->getReplyToken(), $multi);
                } else if ($stateCode == 'askServiceWhen') {
                    $changeJson = $client->request('PUT', SERVICE_URL.'/bot-states', [
                        GuzzleHttp\RequestOptions::JSON => [
                            'id' => $state[0]['id'],
                            'state' => 'askServiceOther',
                        ],
                    ]);

                    $createJson = $client->request('POST', SERVICE_URL.'/bot-state-data', [
                        GuzzleHttp\RequestOptions::JSON => [
                            'userId' => $userId,
                            'state' => 'askServiceWhen',
                            'value' => $replyText,
                        ],
                    ]);

                    $multi = new MultiMessageBuilder();
                    $multi
                        ->add(new TextMessageBuilder('Apakah ada detail lain yang ingin kamu ceritakan?'));
                    $response = $bot->replyMessage($event->getReplyToken(), $multi);
                } else if ($stateCode == 'askServiceOther') {
                    $changeJson = $client->request('PUT', SERVICE_URL.'/bot-states', [
                        GuzzleHttp\RequestOptions::JSON => [
                            'id' => $state[0]['id'],
                            'state' => 'promptYesNo',
                        ],
                    ]);

                    $createJson = $client->request('POST', SERVICE_URL.'/bot-state-data', [
                        GuzzleHttp\RequestOptions::JSON => [
                            'userId' => $userId,
                            'state' => 'askServiceOther',
                            'value' => $replyText,
                        ],
                    ]);

                    // $createJson = $client->request('POST', SERVICE_URL.'/cust-complaints', [
                    //     GuzzleHttp\RequestOptions::JSON => [
                    //         'userId' => $userId,
                    //         'complain' => '',
                    //         'location' => '',
                    //         'when' => '',
                    //         'detail' => '',
                    //     ],
                    // ]);

                    $multi = new MultiMessageBuilder();
                    $multi
                        ->add(new TextMessageBuilder('Baik, abang mohon maaf ya atas ketidaknyamanan yang kamu rasa.. :('))
                        ->add(new TextMessageBuilder('Keluhan kamu sudah abang sampaikan ke tim abang di lapangan. '.
                        'Kamu akan dihubungi lagi melalui email maupun nomor hp'))
                        ->add(newDecisionButtons());
                    $response = $bot->replyMessage($event->getReplyToken(), $multi);
                } else if ($stateCode == 'AskLoginCardNumber') {
                    $changeJson = $client->request('PUT', SERVICE_URL.'/bot-states', [
                        GuzzleHttp\RequestOptions::JSON => [
                            'id' => $state[0]['id'],
                            'state' => 'promptYesNo',
                        ],
                    ]);

                    $createJson = $client->request('POST', SERVICE_URL.'/bot-state-data', [
                        GuzzleHttp\RequestOptions::JSON => [
                            'userId' => $userId,
                            'state' => 'loginSession',
                            'value' => 'Yes',
                        ],
                    ]);

                    $multi = new MultiMessageBuilder();
                    $multi
                        ->add(new TextMessageBuilder('Login berhasil!'))
                        ->add(newDecisionButtons());
                    $response = $bot->replyMessage($event->getReplyToken(), $multi);
                } else if ($stateCode == 'AskRegisterName') {
                    $changeJson = $client->request('PUT', SERVICE_URL.'/bot-states', [
                        GuzzleHttp\RequestOptions::JSON => [
                            'id' => $state[0]['id'],
                            'state' => 'AskRegisterNameCheck',
                        ],
                    ]);

                    $multi = new MultiMessageBuilder();
                    $multi
                        ->add(newRecheckButtons());
                    $response = $bot->replyMessage($event->getReplyToken(), $multi);
                } else if ($stateCode == 'AskRegisterBirth') {
                    $changeJson = $client->request('PUT', SERVICE_URL.'/bot-states', [
                        GuzzleHttp\RequestOptions::JSON => [
                            'id' => $state[0]['id'],
                            'state' => 'AskRegisterBirthCheck',
                        ],
                    ]);

                    $multi = new MultiMessageBuilder();
                    $multi
                        ->add(newRecheckButtons());
                    $response = $bot->replyMessage($event->getReplyToken(), $multi);
                } else if ($stateCode == 'AskRegisterEmail') {
                    $changeJson = $client->request('PUT', SERVICE_URL.'/bot-states', [
                        GuzzleHttp\RequestOptions::JSON => [
                            'id' => $state[0]['id'],
                            'state' => 'AskRegisterEmailCheck',
                        ],
                    ]);

                    $multi = new MultiMessageBuilder();
                    $multi
                        ->add(newRecheckButtons());
                    $response = $bot->replyMessage($event->getReplyToken(), $multi);
                } else if ($stateCode == 'AskRegisterPhone') {
                    $changeJson = $client->request('PUT', SERVICE_URL.'/bot-states', [
                        GuzzleHttp\RequestOptions::JSON => [
                            'id' => $state[0]['id'],
                            'state' => 'promptYesNo',
                        ],
                    ]);

                    $multi = new MultiMessageBuilder();
                    $multi
                        ->add(new TextMessageBuilder('Okee selamat kamu sudah berhasil menjadi member!'))
                        ->add(new ImageMessageBuilder('https://res.cloudinary.com/indonesia-gw/image/upload/v1524300804/cardmypertamina.jpg', 'https://res.cloudinary.com/indonesia-gw/image/upload/v1524300804/cardmypertamina.jpg'))
                        ->add(new TextMessageBuilder('Simpan kartu ini yaa untuk bertransaksi langsung di '.
                        'toko ritel maupun SPBU Pertamina :D'))
                        ->add(newDecisionButtons());
                    $response = $bot->replyMessage($event->getReplyToken(), $multi);
                }
            }
        } else if ($event instanceof PostbackEvent) {
            $value = $event->getPostbackData();

            if ($stateCode == 'mainMenu') {
                if ($value == 'spbuMenu') {
                    $changeJson = $client->request('PUT', SERVICE_URL.'/bot-states', [
                        GuzzleHttp\RequestOptions::JSON => [
                            'id' => $state[0]['id'],
                            'state' => 'askSpbuLocation',
                        ],
                    ]);

                    $replyText = "Dimana lokasi SPBU yang ingin kamu ketahui?";
                    $response = $bot->replyText($event->getReplyToken(), $replyText);
                } else if ($value == 'shopMenu') {
                    $changeJson = $client->request('PUT', SERVICE_URL.'/bot-states', [
                        GuzzleHttp\RequestOptions::JSON => [
                            'id' => $state[0]['id'],
                            'state' => 'promptProductType',
                        ],
                    ]);

                    $multi = new MultiMessageBuilder();
                    $multi
                        ->add(newProductButtons());
                    $response = $bot->replyMessage($event->getReplyToken(), $multi);
                } else if ($value == 'promoMenu') {
                    $changeJson = $client->request('PUT', SERVICE_URL.'/bot-states', [
                        GuzzleHttp\RequestOptions::JSON => [
                            'id' => $state[0]['id'],
                            'state' => 'promptYesNo',
                        ],
                    ]);

                    $promotionsJson = $client->request('GET', SERVICE_URL.'/promotions', ['auth' => ['user', 'pass']]);
                    $decodedResults = json_decode($promotionsJson->getBody()->getContents(), true);
                    $promotions = [];
                    foreach ($decodedResults as $item) {
                        array_push(
                            $promotions, 
                            new CarouselColumnTemplateBuilder(
                                substr($item['title'], 0, 40),
                                substr($item['desc'], 0, 60),
                                $item['image'], 
                                [
                                    new PostbackTemplateActionBuilder('Detail', $item['id']),
                                ]
                            )
                        );
                    }

                    $multi = new MultiMessageBuilder();
                    $multi
                        ->add(new TextMessageBuilder('Okee, Bang Tamin punya promo nih buat kamu!'))
                        ->add(new TemplateMessageBuilder('select promo', new CarouselTemplateBuilder($promotions)))
                        ->add(newDecisionButtons());
                    $response = $bot->replyMessage($event->getReplyToken(), $multi);
                } else if ($value == 'accountMenu') {
                    $loginSession = $client->request('GET', SERVICE_URL.'/bot-state-data?state=loginSession&userId='.$userId, ['auth' => ['user', 'pass']]);
                    $decodedResults = json_decode($loginSession->getBody()->getContents(), true);

                    if (count($decodedResults) > 0) {
                        $changeJson = $client->request('PUT', SERVICE_URL.'/bot-states', [
                            GuzzleHttp\RequestOptions::JSON => [
                                'id' => $state[0]['id'],
                                'state' => 'promptLoggedAccountMenu',
                            ],
                        ]);
    
                        $multi = new MultiMessageBuilder();
                        $multi
                            ->add(newLoggedAccountButtons());
                        $response = $bot->replyMessage($event->getReplyToken(), $multi);
                    } else {
                        $changeJson = $client->request('PUT', SERVICE_URL.'/bot-states', [
                            GuzzleHttp\RequestOptions::JSON => [
                                'id' => $state[0]['id'],
                                'state' => 'promptAccountMenu',
                            ],
                        ]);
    
                        $multi = new MultiMessageBuilder();
                        $multi
                            ->add(newAccountButtons());
                        $response = $bot->replyMessage($event->getReplyToken(), $multi);
                    }
                } else if ($value == 'serviceMenu') {
                    $changeJson = $client->request('PUT', SERVICE_URL.'/bot-states', [
                        GuzzleHttp\RequestOptions::JSON => [
                            'id' => $state[0]['id'],
                            'state' => 'promptService',
                        ],
                    ]);

                    $multi = new MultiMessageBuilder();
                    $multi
                        ->add(newServiceButtons());
                    $response = $bot->replyMessage($event->getReplyToken(), $multi);
                }
            } else if ($stateCode == 'promptYesNo') {
                if ($value == 'yes') {
                    $changeJson = $client->request('PUT', SERVICE_URL.'/bot-states', [
                        GuzzleHttp\RequestOptions::JSON => [
                            'id' => $state[0]['id'],
                            'state' => 'mainMenu',
                        ],
                    ]);

                    $multi = new MultiMessageBuilder();
                    $multi
                        ->add(new TextMessageBuilder('Apa yang bisa abang bantu?'))
                        ->add(newHomeCarousel());
                    $response = $bot->replyMessage($event->getReplyToken(), $multi);
                } else if ($value == 'no') {
                    $changeJson = $client->request('PUT', SERVICE_URL.'/bot-states', [
                        GuzzleHttp\RequestOptions::JSON => [
                            'id' => $state[0]['id'],
                            'state' => 'secondTime',
                        ],
                    ]);

                    $multi = new MultiMessageBuilder();
                    $multi
                        ->add(new TextMessageBuilder('Makasih ya udah hubungin Bang Tamin.' . 
                        ' Kalo ada yang perlu abang bantu, say ‘hai’ aja yaa :D'));
                    $response = $bot->replyMessage($event->getReplyToken(), $multi);
                }
            } else if ($stateCode == 'promptService') {
                $changeJson = $client->request('PUT', SERVICE_URL.'/bot-states', [
                    GuzzleHttp\RequestOptions::JSON => [
                        'id' => $state[0]['id'],
                        'state' => 'askServiceDescription',
                    ],
                ]);

                $createJson = $client->request('POST', SERVICE_URL.'/bot-state-data', [
                    GuzzleHttp\RequestOptions::JSON => [
                        'userId' => $userId,
                        'state' => 'promptService',
                        'value' => $value,
                    ],
                ]);

                $multi = new MultiMessageBuilder();
                $multi
                    ->add(new TextMessageBuilder('Apa masalah yang kamu hadapi?'));
                $response = $bot->replyMessage($event->getReplyToken(), $multi);
            } else if ($stateCode == 'promptProductType') {
                $changeJson = $client->request('PUT', SERVICE_URL.'/bot-states', [
                    GuzzleHttp\RequestOptions::JSON => [
                        'id' => $state[0]['id'],
                        'state' => 'promptYesNo',
                    ],
                ]);

                $productsJson = $client->request('GET', SERVICE_URL.'/products?category='.$value, ['auth' => ['user', 'pass']]);
                $decodedResults = json_decode($productsJson->getBody()->getContents(), true);
                $products = [];
                foreach ($decodedResults as $item) {
                    array_push(
                        $products, 
                        new CarouselColumnTemplateBuilder(
                            substr($item['name'], 0, 40),
                            substr($item['price'], 0, 60),
                            $item['image'], 
                            [
                                new PostbackTemplateActionBuilder('Detail', $item['id']),
                            ]
                        )
                    );
                }

                $multi = new MultiMessageBuilder();
                $multi
                    ->add(new TemplateMessageBuilder('select promo', new CarouselTemplateBuilder($products)))
                    ->add(newDecisionButtons());
                $response = $bot->replyMessage($event->getReplyToken(), $multi);
            } else if ($stateCode == 'promptAccountMenu') {
                if ($value == 'accountRegister') {
                    $changeJson = $client->request('PUT', SERVICE_URL.'/bot-states', [
                        GuzzleHttp\RequestOptions::JSON => [
                            'id' => $state[0]['id'],
                            'state' => 'AskRegisterName',
                        ],
                    ]);

                    $multi = new MultiMessageBuilder();
                    $multi
                        ->add(new TextMessageBuilder('Sipp lah, abang bantu kamu daftar jadi member yak'))
                        ->add(new TextMessageBuilder('Kalo mau membatalkan pendaftaran, ketik ‘batal’ aja yak'))
                        ->add(new TextMessageBuilder('Sekarang, abang butuh nama lengkap kamu. Tolong '.
                        'tuliskan nama lengkap sesuai kartu identitas?'));
                    $response = $bot->replyMessage($event->getReplyToken(), $multi);
                } else if ($value == 'accountLogin') {
                    $changeJson = $client->request('PUT', SERVICE_URL.'/bot-states', [
                        GuzzleHttp\RequestOptions::JSON => [
                            'id' => $state[0]['id'],
                            'state' => 'AskLoginCardNumber',
                        ],
                    ]);

                    $multi = new MultiMessageBuilder();
                    $multi->add(new TextMessageBuilder('Masukkan nomor kartu anda'));
                    $response = $bot->replyMessage($event->getReplyToken(), $multi);
                } else if ($value == 'accountMainMenu') {
                    $changeJson = $client->request('PUT', SERVICE_URL.'/bot-states', [
                        GuzzleHttp\RequestOptions::JSON => [
                            'id' => $state[0]['id'],
                            'state' => 'promptYesNo',
                        ],
                    ]);

                    $multi = new MultiMessageBuilder();
                    $multi->add(newDecisionButtons());
                    $response = $bot->replyMessage($event->getReplyToken(), $multi);
                }
            } else if ($stateCode == 'AskRegisterNameCheck') {
                if ($value == 'yes') {
                    $changeJson = $client->request('PUT', SERVICE_URL.'/bot-states', [
                        GuzzleHttp\RequestOptions::JSON => [
                            'id' => $state[0]['id'],
                            'state' => 'AskRegisterGender',
                        ],
                    ]);

                    $multi = new MultiMessageBuilder();
                    $multi
                        ->add(new TextMessageBuilder('Sepertinya kamu laki-laki sejati nih. Iya gak?'))
                        ->add(newGenderButtons());
                    $response = $bot->replyMessage($event->getReplyToken(), $multi);
                } else if ($value == 'no') {
                    $changeJson = $client->request('PUT', SERVICE_URL.'/bot-states', [
                        GuzzleHttp\RequestOptions::JSON => [
                            'id' => $state[0]['id'],
                            'state' => 'AskRegisterName',
                        ],
                    ]);

                    $multi = new MultiMessageBuilder();
                    $multi
                        ->add(new TextMessageBuilder('Tolong tuliskan ulang nama lengkap sesuai kartu identitas yak'));
                    $response = $bot->replyMessage($event->getReplyToken(), $multi);
                }
            } else if ($stateCode == 'AskRegisterGender') {
                $changeJson = $client->request('PUT', SERVICE_URL.'/bot-states', [
                    GuzzleHttp\RequestOptions::JSON => [
                        'id' => $state[0]['id'],
                        'state' => 'AskRegisterBirth',
                    ],
                ]);

                $multi = new MultiMessageBuilder();
                $multi
                    ->add(new TextMessageBuilder('Okee.. Sekarang, tolong tuliskan tanggal, bulan dan tahun lahir kamu'));
                $response = $bot->replyMessage($event->getReplyToken(), $multi);
            } else if ($stateCode == 'AskRegisterBirthCheck') {
                if ($value == 'yes') {
                    $changeJson = $client->request('PUT', SERVICE_URL.'/bot-states', [
                        GuzzleHttp\RequestOptions::JSON => [
                            'id' => $state[0]['id'],
                            'state' => 'AskRegisterEmail',
                        ],
                    ]);

                    $multi = new MultiMessageBuilder();
                    $multi
                        ->add(new TextMessageBuilder('Siapp! Lalu, apa nama email kamu yang aktif?'));
                    $response = $bot->replyMessage($event->getReplyToken(), $multi);
                } else if ($value == 'no') {
                    $changeJson = $client->request('PUT', SERVICE_URL.'/bot-states', [
                        GuzzleHttp\RequestOptions::JSON => [
                            'id' => $state[0]['id'],
                            'state' => 'AskRegisterBirth',
                        ],
                    ]);

                    $multi = new MultiMessageBuilder();
                    $multi
                        ->add(new TextMessageBuilder('Tolong tuliskan ulang tanggal, bulan dan tahun lahir kamu yak'));
                    $response = $bot->replyMessage($event->getReplyToken(), $multi);
                }
            } else if ($stateCode == 'AskRegisterEmailCheck') {
                if ($value == 'yes') {
                    $changeJson = $client->request('PUT', SERVICE_URL.'/bot-states', [
                        GuzzleHttp\RequestOptions::JSON => [
                            'id' => $state[0]['id'],
                            'state' => 'AskRegisterPhone',
                        ],
                    ]);

                    $multi = new MultiMessageBuilder();
                    $multi
                        ->add(new TextMessageBuilder('Terakhir nih.. Nomor hp kamu berapa?'));
                    $response = $bot->replyMessage($event->getReplyToken(), $multi);
                } else if ($value == 'no') {
                    $changeJson = $client->request('PUT', SERVICE_URL.'/bot-states', [
                        GuzzleHttp\RequestOptions::JSON => [
                            'id' => $state[0]['id'],
                            'state' => 'AskRegisterEmail',
                        ],
                    ]);

                    $multi = new MultiMessageBuilder();
                    $multi
                        ->add(new TextMessageBuilder('Tolong tuliskan ulang email kamu yang aktif yak'));
                    $response = $bot->replyMessage($event->getReplyToken(), $multi);
                }
            } else if ($stateCode == 'promptLoggedAccountMenu') {
                if ($value == 'accountMainMenu') {
                    $changeJson = $client->request('PUT', SERVICE_URL.'/bot-states', [
                        GuzzleHttp\RequestOptions::JSON => [
                            'id' => $state[0]['id'],
                            'state' => 'promptYesNo',
                        ],
                    ]);

                    $multi = new MultiMessageBuilder();
                    $multi->add(newDecisionButtons());
                    $response = $bot->replyMessage($event->getReplyToken(), $multi);
                } else if ($value == 'accountMyCard') {
                    $changeJson = $client->request('PUT', SERVICE_URL.'/bot-states', [
                        GuzzleHttp\RequestOptions::JSON => [
                            'id' => $state[0]['id'],
                            'state' => 'promptYesNo',
                        ],
                    ]);
                    
                    $multi = new MultiMessageBuilder();
                    $multi
                        ->add(new ImageMessageBuilder('https://res.cloudinary.com/indonesia-gw/image/upload/v1524300804/cardmypertamina.jpg', 'https://res.cloudinary.com/indonesia-gw/image/upload/v1524300804/cardmypertamina.jpg'))
                        ->add(newDecisionButtons());
                    $response = $bot->replyMessage($event->getReplyToken(), $multi);
                }
            }
        }
    }

    $res->write('OK');
    return $res;
});

function newGenderButtons() {
    return new TemplateMessageBuilder(
        'gender decision',
        new ButtonTemplateBuilder(
            null,
            'Sepertinya kamu laki-laki sejati nih. Iya gak?',
            null,
            [
                new PostbackTemplateActionBuilder('Yoih', 'yes'),
                new PostbackTemplateActionBuilder('Aku perempuan bang', 'no'),
            ]
        )
    );
}

function newDecisionButtons() {
    return new TemplateMessageBuilder(
        'select decision',
        new ButtonTemplateBuilder(
            null,
            'Ada lagi yang dapat abang bantu?',
            null,
            [
                new PostbackTemplateActionBuilder('Ya', 'yes'),
                new PostbackTemplateActionBuilder('Tidak', 'no'),
            ]
        )
    );
}

function newRecheckButtons() {
    return new TemplateMessageBuilder(
        'check decision',
        new ButtonTemplateBuilder(
            null,
            'Sudah bener nih?',
            null,
            [
                new PostbackTemplateActionBuilder('Sudah', 'yes'),
                new PostbackTemplateActionBuilder('Belum', 'no'),
            ]
        )
    );
}

function newAccountButtons() {
    return new TemplateMessageBuilder(
        'select account menu',
        new ButtonTemplateBuilder(
            null,
            'Kamu harus terdaftar sebagai member atau login terlebih dahulu',
            null,
            [
                new PostbackTemplateActionBuilder('Daftar', 'accountRegister'),
                new PostbackTemplateActionBuilder('Login', 'accountLogin'),
                new PostbackTemplateActionBuilder('Menu utama', 'accountMainMenu'),
            ]
        )
    );
}

function newLoggedAccountButtons() {
    return new TemplateMessageBuilder(
        'select account menu',
        new ButtonTemplateBuilder(
            null,
            'Apa yang ingin kamu lihat?',
            null,
            [
                new PostbackTemplateActionBuilder('Info Poin', 'accountPoint'),
                new PostbackTemplateActionBuilder('Voucher', 'accountVoucher'),
                new PostbackTemplateActionBuilder('Kartu Saya', 'accountMyCard'),
                new PostbackTemplateActionBuilder('Menu Utama', 'accountMainMenu'),
            ]
        )
    );
}

function newServiceButtons() {
    return new TemplateMessageBuilder(
        'select service',
        new ButtonTemplateBuilder(
            null,
            'Apa yang ingin kamu sampaikan?',
            null,
            [
                new PostbackTemplateActionBuilder('Pelayanan', 'serviceService'),
                new PostbackTemplateActionBuilder('Harga & transaksi', 'servicePrice'),
                new PostbackTemplateActionBuilder('Kenyamanan tempat', 'servicePlace'),
                new PostbackTemplateActionBuilder('Lain-lain', 'serviceOther'),
            ]
        )
    );
}

function newProductButtons() {
    return new TemplateMessageBuilder(
        'select product',
        new ButtonTemplateBuilder(
            null,
            'Produk apa yang ingin kamu beli?',
            null,
            [
                new PostbackTemplateActionBuilder('Bright Gas', 'Gas'),
                new PostbackTemplateActionBuilder('Food & drink', 'Food and Beverages'),
                new PostbackTemplateActionBuilder('Oil', 'Oil'),
                new PostbackTemplateActionBuilder('Others', 'Other'),
            ]
        )
    );
}

function newHomeCarousel() {
    return new TemplateMessageBuilder(
        'select main menu', 
        new CarouselTemplateBuilder([
            new CarouselColumnTemplateBuilder(
                null,
                'Info SPBU',
                'https://res.cloudinary.com/indonesia-gw/image/upload/v1524316653/station.png', 
                [
                    new PostbackTemplateActionBuilder('Detail', 'spbuMenu'),
                ]
            ),
            new CarouselColumnTemplateBuilder(
                null,
                'Shop',
                'https://res.cloudinary.com/indonesia-gw/image/upload/v1524316643/shop.png', 
                [
                    new PostbackTemplateActionBuilder('Detail', 'shopMenu'),
                ]
            ),
            new CarouselColumnTemplateBuilder(
                null,
                'Promo',
                'https://res.cloudinary.com/indonesia-gw/image/upload/v1524316632/promo.png', 
                [
                    new PostbackTemplateActionBuilder('Detail', 'promoMenu'),
                ]
            ),
            new CarouselColumnTemplateBuilder(
                null,
                'My Account',
                'https://res.cloudinary.com/indonesia-gw/image/upload/v1524316569/account.png', 
                [
                    new PostbackTemplateActionBuilder('Detail', 'accountMenu'),
                ]
            ),
            new CarouselColumnTemplateBuilder(
                null,
                'Customer Service',
                'https://res.cloudinary.com/indonesia-gw/image/upload/v1524316585/cust_service.png', 
                [
                    new PostbackTemplateActionBuilder('Detail', 'serviceMenu'),
                ]
            ),
        ])
    );
}