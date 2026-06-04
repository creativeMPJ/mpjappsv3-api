<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\EventCustomField;
use App\Models\EventFinanceTransaction;
use App\Models\Speaker;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ApiEventDemoSeeder extends Seeder
{
    public function run(): void
    {
        $speaker = Speaker::updateOrCreate(
            ['nama_lengkap' => 'KH. Ahmad Fahruddin'],
            [
                'id' => Speaker::where('nama_lengkap', 'KH. Ahmad Fahruddin')->value('id') ?: (string) Str::uuid(),
                'alamat' => 'Surabaya, Jawa Timur',
                'keahlian' => ['Jurnalistik Pesantren', 'Public Speaking', 'Manajemen Media'],
                'no_telp' => '6281234567890',
                'portfolio_url' => 'https://mpj.id',
                'kategori' => 'Media Pesantren',
                'bio' => 'Narasumber pelatihan media pesantren dan pengembangan komunitas MPJ.',
            ]
        );

        $events = [
            [
                'slug' => 'pelatihan-jurnalistik-mpj-2026',
                'title' => 'Pelatihan Jurnalistik MPJ 2026',
                'category' => 'Pelatihan',
                'event_type' => 'Non-Kelas',
                'description' => 'Pelatihan menulis berita, reportase, dan publikasi digital untuk kru media pesantren.',
                'location_name' => 'Gedung MPJ Center, Surabaya',
                'location_gmaps' => 'https://maps.google.com/?q=Surabaya',
                'start_date' => now()->addDays(14)->setTime(9, 0),
                'registration_deadline' => now()->addDays(12)->setTime(23, 59),
                'is_open_for_public' => true,
                'is_paid' => true,
                'price_niam' => 0,
                'price_public' => 35000,
                'max_participants' => 120,
                'current_participants' => 0,
                'status_pendaftaran' => 'open',
                'status' => 'LIVE',
                'payment_method' => 'manual',
                'speaker_id' => $speaker->id,
                'certificate_enabled' => true,
                'fields' => [
                    ['label' => 'Ukuran Kaos', 'type' => 'dropdown', 'options' => ['S', 'M', 'L', 'XL', 'XXL'], 'is_required' => true],
                    ['label' => 'Pengalaman Menulis', 'type' => 'long_text', 'options' => [], 'is_required' => false],
                ],
                'finance' => [
                    ['type' => 'expense', 'title' => 'DP Konsumsi', 'amount' => 750000],
                    ['type' => 'expense', 'title' => 'Cetak Banner', 'amount' => 250000],
                ],
            ],
            [
                'slug' => 'rapat-koordinasi-regional-mpj',
                'title' => 'Rapat Koordinasi Regional MPJ',
                'category' => 'Rapat',
                'event_type' => 'Non-Kelas',
                'description' => 'Rapat koordinasi admin regional dan koordinator media pesantren.',
                'location_name' => 'Online via Zoom',
                'location_gmaps' => null,
                'start_date' => now()->addDays(7)->setTime(19, 30),
                'registration_deadline' => now()->addDays(6)->setTime(23, 59),
                'is_open_for_public' => false,
                'is_paid' => false,
                'price_niam' => 0,
                'price_public' => 0,
                'max_participants' => 80,
                'current_participants' => 0,
                'status_pendaftaran' => 'open',
                'status' => 'LIVE',
                'payment_method' => 'manual',
                'speaker_id' => null,
                'certificate_enabled' => false,
                'fields' => [
                    ['label' => 'Regional', 'type' => 'short_text', 'options' => [], 'is_required' => true],
                ],
                'finance' => [],
            ],
            [
                'slug' => 'kemah-film-mpj-2026',
                'title' => 'Kemah Film MPJ 2026',
                'category' => 'Pelatihan',
                'event_type' => 'Sistem Kelas',
                'description' => 'Kelas produksi film pendek untuk santri dan kru media pesantren.',
                'location_name' => 'Batu, Jawa Timur',
                'location_gmaps' => 'https://maps.google.com/?q=Batu+Jawa+Timur',
                'start_date' => now()->addDays(30)->setTime(8, 0),
                'registration_deadline' => now()->addDays(24)->setTime(23, 59),
                'is_open_for_public' => true,
                'is_paid' => true,
                'price_niam' => 50000,
                'price_public' => 75000,
                'max_participants' => 60,
                'current_participants' => 0,
                'status_pendaftaran' => 'open',
                'status' => 'APPROVED',
                'payment_method' => 'manual',
                'speaker_id' => $speaker->id,
                'certificate_enabled' => true,
                'fields' => [
                    ['label' => 'Bidang Minat', 'type' => 'radio', 'options' => ['Sutradara/Script', 'DOP/Editor', 'Keduanya'], 'is_required' => true],
                    ['label' => 'Link Karya', 'type' => 'short_text', 'options' => [], 'is_required' => false],
                ],
                'finance' => [
                    ['type' => 'expense', 'title' => 'Booking Lokasi', 'amount' => 1500000],
                ],
            ],
        ];

        foreach ($events as $eventData) {
            $fields = $eventData['fields'];
            $financeRows = $eventData['finance'];
            unset($eventData['fields'], $eventData['finance']);

            $event = Event::updateOrCreate(
                ['title' => $eventData['title']],
                [
                    'id' => Event::where('title', $eventData['title'])->value('id') ?: (string) Str::uuid(),
                    'name' => $eventData['title'],
                    'category' => $eventData['category'],
                    'event_type' => $eventData['event_type'],
                    'description' => $eventData['description'],
                    'location' => $eventData['location_name'],
                    'location_name' => $eventData['location_name'],
                    'location_gmaps' => $eventData['location_gmaps'],
                    'date' => $eventData['start_date'],
                    'start_date' => $eventData['start_date'],
                    'registration_deadline' => $eventData['registration_deadline'],
                    'is_open_for_public' => $eventData['is_open_for_public'],
                    'is_paid' => $eventData['is_paid'],
                    'member_price' => $eventData['price_niam'],
                    'public_price' => $eventData['price_public'],
                    'price_niam' => $eventData['price_niam'],
                    'price_public' => $eventData['price_public'],
                    'max_participants' => $eventData['max_participants'],
                    'current_participants' => $eventData['current_participants'],
                    'status_pendaftaran' => $eventData['status_pendaftaran'],
                    'status' => $eventData['status'],
                    'payment_method' => $eventData['payment_method'],
                    'speaker_id' => $eventData['speaker_id'],
                    'certificate_enabled' => $eventData['certificate_enabled'],
                ]
            );

            $event->customFields()->delete();
            foreach ($fields as $index => $field) {
                EventCustomField::create([
                    'event_id' => $event->id,
                    'label' => $field['label'],
                    'type' => $field['type'],
                    'options' => $field['options'],
                    'is_required' => $field['is_required'],
                    'order_num' => $index,
                ]);
            }

            foreach ($financeRows as $row) {
                EventFinanceTransaction::updateOrCreate(
                    ['event_id' => $event->id, 'source' => 'manual', 'title' => $row['title']],
                    [
                        'type' => $row['type'],
                        'description' => 'Data demo awal untuk kebutuhan integrasi frontend.',
                        'amount' => $row['amount'],
                        'status' => 'posted',
                        'transaction_date' => now(),
                    ]
                );
            }
        }
    }
}
