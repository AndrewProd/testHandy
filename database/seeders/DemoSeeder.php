<?php

namespace Database\Seeders;

use App\Domain\DripCampaign\Enums\CampaignStatus;
use App\Domain\DripCampaign\Enums\DeliveryChannel;
use App\Domain\DripCampaign\Enums\LeadStatus;
use App\Domain\DripCampaign\Models\DripCampaign;
use App\Domain\FieldService\Enums\FieldServiceRequestStatus;
use App\Domain\FieldService\Enums\FieldServiceSource;
use App\Domain\FieldService\Enums\FieldServiceType;
use App\Domain\FieldService\Enums\FieldVisitStatus;
use App\Domain\FieldService\Models\FieldServiceRequest;
use App\Domain\FieldService\Models\FieldTeam;
use App\Domain\FieldService\Models\FieldVisit;
use App\Domain\Conversations\Enums\MessageDirection;
use App\Domain\Conversations\Enums\MessageSenderType;
use App\Domain\Conversations\Models\ConversationMessage;
use App\Domain\ReplyIntake\Enums\Intent;
use App\Models\CampaignEnrollment;
use App\Models\Client;
use App\Models\ReplyTask;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoSeeder extends Seeder
{
    private const CLIENTS = [
        ['tenant_id' => 42, 'email' => 'd.walker@northshore-homes.ca',   'name' => 'Dana',   'step' => 2],
        ['tenant_id' => 42, 'email' => 'm.tremblay@lakesideprop.ca',     'name' => 'Marc',   'step' => 1],
        ['tenant_id' => 42, 'email' => 'r.osei@maplecourt.ca',           'name' => 'Rita',   'step' => 3],
        ['tenant_id' => 42, 'email' => 'j.kowalczyk@bridgeportbuild.ca', 'name' => 'Jan',    'step' => 2],
        ['tenant_id' => 42, 'email' => 's.lindqvist@harbourview.ca',     'name' => 'Sofia',  'step' => 1],
        ['tenant_id' => 42, 'email' => 'a.ferreira@stonegate.ca',        'name' => 'Ana',    'step' => 2],
        ['tenant_id' => 42, 'email' => 'l.beaulieu@ridgetop.ca',         'name' => 'Luc',    'step' => 3],
    ];

    public function run(): void
    {
        if (!DripCampaign::query()->exists()) {
            $this->seedCampaigns();
        } else {
            $this->alignCampaignFlow();
        }

        if (!Client::query()->exists()) {
            $this->seedClients();
        }

        $this->fillClientAddresses();

        if (!FieldTeam::query()->exists()) {
            $this->seedFieldTeams();
        }

        if (!app()->runningUnitTests() && !ReplyTask::query()->exists()) {
            $this->seedReplyTasks();
        }

        if (!app()->runningUnitTests() && !FieldServiceRequest::query()->exists()) {
            $this->seedFieldRequests();
        }

        if (!app()->runningUnitTests() && !ConversationMessage::query()->exists()) {
            $this->seedConversationMessages();
        }
    }

    private function seedClients(): void
    {
        foreach (self::CLIENTS as $row) {
            $client = Client::create([
                'tenant_id' => $row['tenant_id'],
                'email'     => $row['email'],
                'name'      => $row['name'],
            ]);

            CampaignEnrollment::create([
                'tenant_id'    => $row['tenant_id'],
                'client_id'    => $client->id,
                'campaign_id'  => 7,
                'current_step' => $row['step'],
                'status'       => 'active',
                'next_send_at' => now()->addDays(3),
            ]);
        }

        $mensah = Client::create([
            'tenant_id'     => 42,
            'email'         => 'k.mensah@fairlawn.ca',
            'name'          => 'Kwame',
            'suppressed_at' => now()->subDays(3),
        ]);

        CampaignEnrollment::create([
            'tenant_id'    => 42,
            'client_id'    => $mensah->id,
            'campaign_id'  => 7,
            'current_step' => 1,
            'status'       => 'stopped',
            'next_send_at' => null,
        ]);
    }

    private function seedCampaigns(): void
    {
        $this->insertCampaign(7, $this->dormantQuoteCampaign());

        $this->insertCampaign(9, [
            'name' => 'Expired quotes · Q3 win-back',
            'description' => 'Win-back for quotes that expired last quarter.',
            'status' => CampaignStatus::Active,
            'steps' => [
                [
                    'status' => LeadStatus::Dormant,
                    'delay_minutes' => 0,
                    'channel' => DeliveryChannel::Email,
                    'subject' => 'Your window quote is still on file',
                    'message' => 'Hi {{name}}, your previous quote is still available. Want us to refresh the numbers?',
                ],
                [
                    'status' => LeadStatus::Contacted,
                    'delay_minutes' => 7200,
                    'channel' => DeliveryChannel::Email,
                    'subject' => 'Updated pricing for your quote',
                    'message' => 'Hi {{name}}, we can send an updated package this week if the project is still on your list.',
                ],
                [
                    'status' => LeadStatus::Interested,
                    'delay_minutes' => 2880,
                    'channel' => DeliveryChannel::Whatsapp,
                    'subject' => null,
                    'message' => 'Hi {{name}}, happy to book a call and walk through the refreshed quote.',
                ],
            ],
        ]);

        $this->insertCampaign(11, [
            'name' => 'Cold list · spring 2026',
            'description' => 'Longer nurture for the cold spring list. Currently paused.',
            'status' => CampaignStatus::Paused,
            'steps' => [
                [
                    'status' => LeadStatus::Dormant,
                    'delay_minutes' => 0,
                    'channel' => DeliveryChannel::Email,
                    'subject' => 'Planning window work this spring?',
                    'message' => 'Hi {{name}}, spring is the busy season for window replacements. We can help you plan ahead.',
                ],
                [
                    'status' => LeadStatus::Contacted,
                    'delay_minutes' => 5760,
                    'channel' => DeliveryChannel::Email,
                    'subject' => 'What a site measure looks like',
                    'message' => 'Hi {{name}}, a measure visit takes about 45 minutes and gives you a firm number.',
                ],
                [
                    'status' => LeadStatus::Question,
                    'delay_minutes' => 5760,
                    'channel' => DeliveryChannel::Email,
                    'subject' => 'Answers to the questions we hear most',
                    'message' => 'Hi {{name}}, here are the most common questions about lead times, energy ratings, and install.',
                ],
                [
                    'status' => LeadStatus::NotNow,
                    'delay_minutes' => 10080,
                    'channel' => DeliveryChannel::Sms,
                    'subject' => null,
                    'message' => 'Hi {{name}}, no rush — reply if you want us to check back later this spring.',
                ],
                [
                    'status' => LeadStatus::Qualified,
                    'delay_minutes' => 1440,
                    'channel' => DeliveryChannel::Whatsapp,
                    'subject' => null,
                    'message' => 'Hi {{name}}, we can put you on the spring calendar if you still want a visit.',
                ],
            ],
        ]);

        $this->resetSequence('drip_campaigns');
        $this->resetSequence('drip_campaign_steps');
    }

    private function dormantQuoteCampaign(): array
    {
        return [
            'name' => 'Dormant leads · window quotes',
            'description' => 'Time-based follow-up while there is no reply. A customer reply stops the drip and opens Reply Center; measurement intent can open a field request.',
            'status' => CampaignStatus::Active,
            'entry_status' => LeadStatus::Dormant,
            'stop_on_reply' => true,
            'handoff_intents' => DripCampaign::defaultHandoffIntents(),
            'field_intents' => DripCampaign::defaultFieldIntents(),
            'suppress_intents' => DripCampaign::defaultSuppressIntents(),
            'steps' => [
                [
                    'status' => LeadStatus::Dormant,
                    'delay_minutes' => 0,
                    'channel' => DeliveryChannel::Email,
                    'subject' => 'Your window quote is still here',
                    'message' => 'Hi {{name}}, we still have your window quote on file. Reply to this email and a manager will pick it up — we can also send someone to measure.',
                ],
                [
                    'status' => LeadStatus::Dormant,
                    'delay_minutes' => 2880,
                    'channel' => DeliveryChannel::Email,
                    'subject' => 'Want a callback or an on-site measure?',
                    'message' => 'Hi {{name}}, still thinking it over? Reply and we will call you, or we can book a measure visit with a field team.',
                ],
                [
                    'status' => LeadStatus::Dormant,
                    'delay_minutes' => 4320,
                    'channel' => DeliveryChannel::Email,
                    'subject' => 'A few packages — and a technician if you want one',
                    'message' => 'Hi {{name}}, here are a few window packages. If you want numbers that fit the openings, reply “measure” and we will schedule a visit.',
                ],
                [
                    'status' => LeadStatus::Dormant,
                    'delay_minutes' => 7200,
                    'channel' => DeliveryChannel::Sms,
                    'subject' => null,
                    'message' => 'Last note from Northshore, {{name}}: reply to this and a manager takes over. Unsubscribe anytime.',
                ],
            ],
        ];
    }

    private function alignCampaignFlow(): void
    {
        $campaign = DripCampaign::query()->find(7);
        if ($campaign === null) {
            return;
        }

        $data = $this->dormantQuoteCampaign();
        $campaign->fill([
            'name' => $data['name'],
            'description' => $data['description'],
            'status' => $data['status'],
            'entry_status' => $data['entry_status'],
            'stop_on_reply' => $data['stop_on_reply'],
            'handoff_intents' => $data['handoff_intents'],
            'field_intents' => $data['field_intents'],
            'suppress_intents' => $data['suppress_intents'],
        ])->save();

        $campaign->steps()->delete();
        foreach ($data['steps'] as $index => $step) {
            $campaign->steps()->create([
                'position' => $index + 1,
                'status' => $step['status'],
                'delay_minutes' => $step['delay_minutes'],
                'channel' => $step['channel'],
                'subject' => $step['subject'],
                'message' => $step['message'],
                'is_active' => true,
            ]);
        }
    }

    private function insertCampaign(int $id, array $data): void
    {
        $campaign = new DripCampaign();
        $campaign->id = $id;
        $campaign->tenant_id = 42;
        $campaign->name = $data['name'];
        $campaign->description = $data['description'];
        $campaign->status = $data['status'];
        $campaign->entry_status = $data['entry_status'] ?? LeadStatus::Dormant;
        $campaign->stop_on_reply = $data['stop_on_reply'] ?? true;
        $campaign->handoff_intents = $data['handoff_intents'] ?? DripCampaign::defaultHandoffIntents();
        $campaign->field_intents = $data['field_intents'] ?? DripCampaign::defaultFieldIntents();
        $campaign->suppress_intents = $data['suppress_intents'] ?? DripCampaign::defaultSuppressIntents();
        $campaign->save();

        foreach ($data['steps'] as $index => $step) {
            $campaign->steps()->create([
                'position' => $index + 1,
                'status' => $step['status'],
                'delay_minutes' => $step['delay_minutes'],
                'channel' => $step['channel'],
                'subject' => $step['subject'],
                'message' => $step['message'],
                'is_active' => true,
            ]);
        }
    }

    private function resetSequence(string $table): void
    {
        DB::statement("SELECT setval(pg_get_serial_sequence('{$table}', 'id'), COALESCE((SELECT MAX(id) FROM {$table}), 1))");
    }

    private function fillClientAddresses(): void
    {
        $addresses = [
            'd.walker@northshore-homes.ca' => [
                'name' => 'Dana Walker',
                'address' => '18 Finch Ave W',
                'city' => 'North York',
                'region' => 'Toronto North',
                'postal_code' => 'M2N 2G9',
                'latitude' => 43.7807,
                'longitude' => -79.4163,
            ],
            'm.tremblay@lakesideprop.ca' => [
                'name' => 'Marc Tremblay',
                'address' => '412 Lakeshore Rd W',
                'city' => 'Mississauga',
                'region' => 'Mississauga / Oakville',
                'postal_code' => 'L5H 1G1',
                'latitude' => 43.5448,
                'longitude' => -79.6164,
            ],
            'r.osei@maplecourt.ca' => [
                'name' => 'Rita Osei',
                'address' => '90 Maplewood Ave',
                'city' => 'Toronto',
                'region' => 'Toronto North',
                'postal_code' => 'M6C 1J8',
                'latitude' => 43.6872,
                'longitude' => -79.4255,
            ],
            'j.kowalczyk@bridgeportbuild.ca' => [
                'name' => 'Jan Kowalczyk',
                'address' => '55 Trafalgar Rd',
                'city' => 'Oakville',
                'region' => 'Mississauga / Oakville',
                'postal_code' => 'L6J 3J3',
                'latitude' => 43.4675,
                'longitude' => -79.6877,
            ],
            's.lindqvist@harbourview.ca' => [
                'name' => 'Sofia Lindqvist',
                'address' => '210 Queen St',
                'city' => 'Ottawa',
                'region' => 'Ottawa',
                'postal_code' => 'K1P 5E3',
                'latitude' => 45.4209,
                'longitude' => -75.6972,
            ],
            'a.ferreira@stonegate.ca' => [
                'name' => 'Ana Ferreira',
                'address' => '7 Stonegate Dr',
                'city' => 'Etobicoke',
                'region' => 'Mississauga / Oakville',
                'postal_code' => 'M8Y 3L4',
                'latitude' => 43.6288,
                'longitude' => -79.5053,
            ],
            'l.beaulieu@ridgetop.ca' => [
                'name' => 'Luc Beaulieu',
                'address' => '1400 Bank St',
                'city' => 'Ottawa',
                'region' => 'Ottawa',
                'postal_code' => 'K1H 7Y2',
                'latitude' => 45.3866,
                'longitude' => -75.6769,
            ],
            'k.mensah@fairlawn.ca' => [
                'name' => 'Kwame Mensah',
                'address' => '33 Fairlawn Ave',
                'city' => 'North York',
                'region' => 'Toronto North',
                'postal_code' => 'M5M 1S6',
                'latitude' => 43.7284,
                'longitude' => -79.4192,
            ],
        ];

        foreach ($addresses as $email => $row) {
            Client::query()->where('email', $email)->update($row);
        }
    }

    private function seedFieldTeams(): void
    {
        $teams = [
            [
                'name' => 'GTA North',
                'region' => 'Toronto North',
                'latitude' => 43.7615,
                'longitude' => -79.4111,
                'members' => [
                    ['name' => 'Pat Singh', 'role' => 'lead', 'skills' => ['Window Measurement', 'Consultation']],
                    ['name' => 'Nina Cole', 'role' => 'technician', 'skills' => ['Window Measurement']],
                    ['name' => 'Omar Haddad', 'role' => 'estimator', 'skills' => ['Quote Review', 'Consultation']],
                    ['name' => 'Leah Grant', 'role' => 'technician', 'skills' => ['Window Measurement', 'Install']],
                ],
            ],
            [
                'name' => 'GTA West',
                'region' => 'Mississauga / Oakville',
                'latitude' => 43.5890,
                'longitude' => -79.6441,
                'members' => [
                    ['name' => 'Chris Alvarez', 'role' => 'lead', 'skills' => ['Window Measurement', 'Quote Review']],
                    ['name' => 'Priya Shah', 'role' => 'technician', 'skills' => ['Window Measurement', 'Consultation']],
                    ['name' => 'Ben Walsh', 'role' => 'technician', 'skills' => ['Install', 'Quote Review']],
                ],
            ],
            [
                'name' => 'Ottawa',
                'region' => 'Ottawa',
                'latitude' => 45.4215,
                'longitude' => -75.6972,
                'members' => [
                    ['name' => 'Amelie Roy', 'role' => 'lead', 'skills' => ['Window Measurement', 'Consultation']],
                    ['name' => 'Jonah Clarke', 'role' => 'technician', 'skills' => ['Window Measurement', 'Quote Review']],
                ],
            ],
        ];

        foreach ($teams as $row) {
            $team = FieldTeam::query()->create([
                'tenant_id' => 42,
                'name' => $row['name'],
                'region' => $row['region'],
                'services' => [
                    FieldServiceType::Measurement->value,
                    FieldServiceType::Consultation->value,
                    FieldServiceType::QuoteReview->value,
                ],
                'work_starts_at' => '08:00:00',
                'work_ends_at' => '18:00:00',
                'daily_capacity' => 5,
                'latitude' => $row['latitude'],
                'longitude' => $row['longitude'],
                'is_active' => true,
            ]);

            foreach ($row['members'] as $member) {
                $team->members()->create($member + ['is_active' => true]);
            }
        }
    }

    private function seedReplyTasks(): void
    {
        $byEmail = Client::query()->where('tenant_id', 42)->get()->keyBy('email');

        $rows = [
            ['email' => 'd.walker@northshore-homes.ca', 'event_id' => 'evt_01HZ8A0001', 'sentiment' => 'interested', 'intent' => Intent::Interested, 'confidence' => 0.91, 'status' => 'in_progress', 'assignee_id' => 2, 'campaign_id' => 7, 'hours' => 2, 'body' => 'Sounds good. Can you call me Thursday afternoon? I still have the quote.'],
            ['email' => 'm.tremblay@lakesideprop.ca', 'event_id' => 'evt_01HZ8A0002', 'sentiment' => 'question', 'intent' => Intent::Question, 'confidence' => 0.88, 'status' => 'open', 'assignee_id' => null, 'campaign_id' => 7, 'hours' => 4, 'body' => 'How much would it be for eight windows on the second floor?'],
            ['email' => 'r.osei@maplecourt.ca', 'event_id' => 'evt_01HZ8A0003', 'sentiment' => 'unsubscribe', 'intent' => Intent::Unsubscribe, 'confidence' => 1.0, 'status' => 'resolved', 'assignee_id' => 1, 'campaign_id' => 7, 'hours' => 25, 'body' => 'Please take me off your list, I do not want any more emails.'],
            ['email' => 'j.kowalczyk@bridgeportbuild.ca', 'event_id' => 'evt_01HZ8A0004', 'sentiment' => null, 'intent' => null, 'confidence' => null, 'status' => 'open', 'assignee_id' => null, 'campaign_id' => 9, 'hours' => 27, 'body' => 'Thanks — will discuss internally and revert.'],
            ['email' => 's.lindqvist@harbourview.ca', 'event_id' => 'evt_01HZ8A0005', 'sentiment' => 'auto_reply', 'intent' => Intent::AutoReply, 'confidence' => 1.0, 'status' => 'dismissed', 'assignee_id' => 3, 'campaign_id' => 7, 'hours' => 48, 'body' => 'I am out of office until Monday with limited access to email.'],
            ['email' => 'a.ferreira@stonegate.ca', 'event_id' => 'evt_01HZ8A0006', 'sentiment' => 'not_now', 'intent' => Intent::NotNow, 'confidence' => 0.84, 'status' => 'waiting_customer', 'assignee_id' => 3, 'campaign_id' => 9, 'hours' => 53, 'body' => 'Not this year, our renovation budget is spent. Maybe spring.'],
            ['email' => 'l.beaulieu@ridgetop.ca', 'event_id' => 'evt_01HZ8A0007', 'sentiment' => 'wrong_person', 'intent' => Intent::WrongPerson, 'confidence' => 0.97, 'status' => 'resolved', 'assignee_id' => 1, 'campaign_id' => 7, 'hours' => 74, 'body' => 'You have the wrong person, I never requested a quote.'],
            ['email' => 'k.mensah@fairlawn.ca', 'event_id' => 'evt_01HZ8A0012', 'sentiment' => null, 'intent' => null, 'confidence' => null, 'status' => 'open', 'assignee_id' => null, 'campaign_id' => 7, 'hours' => 78, 'body' => 'Could you resend the brochure? The link expired.'],
        ];

        foreach ($rows as $row) {
            $client = $byEmail->get($row['email']);
            if ($client === null) {
                continue;
            }

            ReplyTask::query()->create([
                'tenant_id' => 42,
                'client_id' => $client->id,
                'event_id' => $row['event_id'],
                'sentiment' => $row['sentiment'],
                'intent' => $row['intent'],
                'confidence' => $row['confidence'],
                'body' => $row['body'],
                'status' => $row['status'],
                'assignee_id' => $row['assignee_id'],
                'campaign_id' => $row['campaign_id'],
                'created_at' => now()->subHours($row['hours']),
                'updated_at' => now()->subHours($row['hours']),
            ]);
        }
    }

    private function seedConversationMessages(): void
    {
        foreach (ReplyTask::query()->orderBy('id')->get() as $task) {
            $at = $task->created_at ?? now();

            ConversationMessage::query()->create([
                'tenant_id' => $task->tenant_id,
                'reply_task_id' => $task->id,
                'sender_type' => MessageSenderType::Customer,
                'sender_id' => $task->client_id,
                'direction' => MessageDirection::Inbound,
                'channel' => 'email',
                'body' => $task->body ?: '(empty reply)',
                'ai_generated' => false,
                'external_message_id' => $task->event_id,
                'created_at' => $at,
                'updated_at' => $at,
            ]);

            $intent = $task->intent?->value ?? $task->sentiment;
            if ($intent) {
                ConversationMessage::query()->create([
                    'tenant_id' => $task->tenant_id,
                    'reply_task_id' => $task->id,
                    'sender_type' => MessageSenderType::Ai,
                    'direction' => MessageDirection::Internal,
                    'channel' => 'email',
                    'body' => sprintf(
                        'Intent %s · sentiment %s · confidence %s. Copilot only — a manager must send the customer reply.',
                        $intent,
                        $task->sentiment ?? 'unclassified',
                        $task->confidence !== null ? number_format((float) $task->confidence, 2) : 'n/a',
                    ),
                    'ai_generated' => true,
                    'metadata' => [
                        'intent' => $intent,
                        'sentiment' => $task->sentiment,
                        'confidence' => $task->confidence,
                    ],
                    'created_at' => $at->copy()->addSeconds(2),
                    'updated_at' => $at->copy()->addSeconds(2),
                ]);
            }
        }
    }

    private function seedFieldRequests(): void
    {
        $jan = Client::query()->where('email', 'j.kowalczyk@bridgeportbuild.ca')->first();
        $west = FieldTeam::query()->where('name', 'GTA West')->first();

        if ($jan === null || $west === null) {
            return;
        }

        $fsr = FieldServiceRequest::query()->create([
            'tenant_id' => 42,
            'client_id' => $jan->id,
            'reply_task_id' => ReplyTask::query()->where('event_id', 'evt_01HZ8A0004')->value('id'),
            'type' => FieldServiceType::QuoteReview,
            'status' => FieldServiceRequestStatus::Scheduled,
            'source' => FieldServiceSource::Manager,
            'address' => $jan->address,
            'city' => $jan->city,
            'region' => $jan->region,
            'postal_code' => $jan->postal_code,
            'latitude' => $jan->latitude,
            'longitude' => $jan->longitude,
            'requested_window' => 'next week',
            'requested_at' => now()->addDays(4)->setTime(14, 0),
            'assigned_team_id' => $west->id,
        ]);

        FieldVisit::query()->create([
            'tenant_id' => 42,
            'field_service_request_id' => $fsr->id,
            'team_id' => $west->id,
            'scheduled_at' => now()->addDays(4)->setTime(14, 0),
            'status' => FieldVisitStatus::Scheduled,
            'distance_km' => 14.6,
        ]);
    }
}
