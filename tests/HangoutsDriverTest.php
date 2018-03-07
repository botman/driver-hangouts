<?php

namespace Tests;

use BotMan\BotMan\Messages\Attachments\Image;
use BotMan\BotMan\Messages\Outgoing\OutgoingMessage;
use Mockery as m;
use BotMan\BotMan\Http\Curl;
use PHPUnit_Framework_TestCase;
use BotMan\Drivers\Hangouts\HangoutsDriver;
use Symfony\Component\HttpFoundation\Request;

class HangoutsDriverTest extends PHPUnit_Framework_TestCase
{
    const TEST_TOKEN = 'this-is-a-test-token';

    public function tearDown()
    {
        m::close();
    }

    private function getValidDriver($fixture = 'dm', $config = null)
    {
        $config = $config ?? [
            'token' => self::TEST_TOKEN
        ];

        $request = m::mock(Request::class.'[getContent]');
        $request->shouldReceive('getContent')->andReturn(file_get_contents(__DIR__.'/fixtures/'.$fixture.'.json'));
        $htmlInterface = m::mock(Curl::class);

        return new HangoutsDriver($request, [
            'hangouts' => $config,
        ], $htmlInterface);
    }

    private function getDriver($responseData, $htmlInterface = null)
    {
        $request = m::mock(Request::class.'[getContent]');
        $request->shouldReceive('getContent')->andReturn(json_encode($responseData));
        if ($htmlInterface === null) {
            $htmlInterface = m::mock(Curl::class);
        }

        return new HangoutsDriver($request, [], $htmlInterface);
    }

    /** @test */
    public function it_returns_the_driver_name()
    {
        $driver = $this->getDriver([]);
        $this->assertSame('Hangouts', $driver->getName());
    }

    /** @test */
    public function it_matches_the_request()
    {
        $driver = $this->getDriver([
            'messages' => [
                ['text' => 'bar'],
                ['text' => 'foo'],
            ],
        ]);
        $this->assertFalse($driver->matchesRequest());

        $driver = $this->getValidDriver();
        $this->assertTrue($driver->matchesRequest());
    }

    /** @test */
    public function it_returns_the_message_object()
    {
        $driver = $this->getValidDriver();

        $this->assertTrue(is_array($driver->getMessages()));
    }

    /** @test */
    public function it_returns_the_message_text()
    {
        $driver = $this->getValidDriver();

        $this->assertSame('Hi', $driver->getMessages()[0]->getText());
    }

    /** @test */
    public function it_strips_annotations()
    {
        $driver = $this->getValidDriver('annotation', [
            'token' => self::TEST_TOKEN,
            'strip_annotations' => false
        ]);

        $this->assertSame('hey @botman hi', $driver->getMessages()[0]->getText());

        $driver = $this->getValidDriver('annotation', [
            'token' => self::TEST_TOKEN,
            'strip_annotations' => true
        ]);

        $this->assertSame('hi', $driver->getMessages()[0]->getText());
    }

    /** @test */
    public function it_returns_the_user_id()
    {
        $driver = $this->getValidDriver();

        $this->assertSame('users/1133439002168811', $driver->getMessages()[0]->getSender());
    }

    /** @test */
    public function it_returns_the_recipient_id()
    {
        $driver = $this->getValidDriver();

        $this->assertSame('spaces/3YQwSXAAAAE/messages/-Kzy_s1U9I.-Kzy_s1U9zI', $driver->getMessages()[0]->getRecipient());
    }

    /** @test */
    public function it_returns_the_user()
    {
        $driver = $this->getValidDriver();

        $user = $driver->getUser($driver->getMessages()[0]);

        $this->assertSame('users/1133439002168811', $user->getId());
        $this->assertNull($user->getFirstName());
        $this->assertNull($user->getLastName());
        $this->assertSame('Marcel Pociot', $user->getUsername());
    }

    /** @test */
    public function it_can_reply_string_messages()
    {
        $driver = $this->getValidDriver();

        $outgoing = new OutgoingMessage('test');

        $response = $driver->sendPayload($driver->buildServicePayload($outgoing, $driver->getMessages()[0]));

        $this->assertSame('{"text":"test","cards":[{"sections":[{"widgets":[]}]}]}', $response->getContent());
    }

    /** @test */
    public function it_can_reply_images()
    {
        $driver = $this->getValidDriver();

        $outgoing = new OutgoingMessage('test');
        $outgoing->withAttachment(Image::url('http://foo.jpg'));

        $response = $driver->sendPayload($driver->buildServicePayload($outgoing, $driver->getMessages()[0]));

        $this->assertSame('{"text":"test","cards":[{"sections":[{"widgets":[{"image":{"imageUrl":"http:\/\/foo.jpg"}}]}]}]}', $response->getContent());
    }
}
