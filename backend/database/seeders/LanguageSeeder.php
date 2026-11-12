<?php

namespace Database\Seeders;

use App\Models\CulturalTip;
use App\Models\Phrase;
use Illuminate\Database\Seeder;

/**
 * R15: phrasebook for 10 languages (16 phrases each, in the order of ENGLISH)
 * and etiquette notes for 10 countries. Run: php artisan db:seed --class=LanguageSeeder
 */
class LanguageSeeder extends Seeder
{
    /** @var array<string, list<string>> */
    private const ENGLISH = [
        'greetings' => ['Hello', 'Thank you', 'Excuse me / Sorry', 'Goodbye'],
        'food' => ['The menu, please', 'The bill, please', 'I am vegetarian', 'Water, please'],
        'directions' => ['Where is the train station?', 'Where is the restroom?', 'Is it far from here?', 'Please take me to this address'],
        'emergencies' => ['Help!', 'Call the police!', 'I need a doctor', 'Where is the hospital?'],
    ];

    public function run(): void
    {
        Phrase::query()->delete();
        CulturalTip::query()->delete();

        $english = [];
        foreach (self::ENGLISH as $category => $phrases) {
            foreach ($phrases as $phrase) {
                $english[] = [$category, $phrase];
            }
        }

        foreach ($this->languages() as $code => $language) {
            foreach ($language['phrases'] as $index => [$translation, $romanization, $pronunciation]) {
                Phrase::create([
                    'language_code' => $code,
                    'language_name' => $language['name'],
                    'category' => $english[$index][0],
                    'english' => $english[$index][1],
                    'translation' => $translation,
                    'romanization' => $romanization,
                    'pronunciation' => $pronunciation,
                ]);
            }
        }

        foreach ($this->tips() as [$country, $languageCode, $topics]) {
            foreach ($topics as $topic => [$title, $body]) {
                CulturalTip::create([
                    'country' => $country,
                    'language_code' => $languageCode,
                    'topic' => $topic,
                    'title' => $title,
                    'body' => $body,
                ]);
            }
        }
    }

    /**
     * @return array<string, array{name: string, phrases: list<array{0: string, 1: ?string, 2: string}>}>
     */
    private function languages(): array
    {
        return [
            'es' => ['name' => 'Spanish', 'phrases' => [
                ['Hola', null, 'OH-lah'],
                ['Gracias', null, 'GRAH-syahs'],
                ['Perdón', null, 'pehr-DOHN'],
                ['Adiós', null, 'ah-DYOHS'],
                ['La carta, por favor', null, 'lah KAR-tah, por fah-VOR'],
                ['La cuenta, por favor', null, 'lah KWEN-tah, por fah-VOR'],
                ['Soy vegetariano / vegetariana', null, 'soy beh-heh-tah-RYAH-noh / -nah'],
                ['Agua, por favor', null, 'AH-gwah, por fah-VOR'],
                ['¿Dónde está la estación de tren?', null, 'DOHN-deh es-TAH lah es-tah-SYOHN deh tren'],
                ['¿Dónde está el baño?', null, 'DOHN-deh es-TAH el BAH-nyoh'],
                ['¿Está lejos de aquí?', null, 'es-TAH LEH-hohs deh ah-KEE'],
                ['Lléveme a esta dirección, por favor', null, 'YEH-veh-meh ah ES-tah dee-rek-SYOHN, por fah-VOR'],
                ['¡Ayuda!', null, 'ah-YOO-dah'],
                ['¡Llame a la policía!', null, 'YAH-meh ah lah poh-lee-SEE-ah'],
                ['Necesito un médico', null, 'neh-seh-SEE-toh oon MEH-dee-koh'],
                ['¿Dónde está el hospital?', null, 'DOHN-deh es-TAH el ohs-pee-TAHL'],
            ]],
            'fr' => ['name' => 'French', 'phrases' => [
                ['Bonjour', null, 'bohn-ZHOOR'],
                ['Merci', null, 'mehr-SEE'],
                ['Excusez-moi', null, 'ex-kew-ZAY mwah'],
                ['Au revoir', null, 'oh ruh-VWAHR'],
                ['La carte, s\'il vous plaît', null, 'lah KART, seel voo PLEH'],
                ['L\'addition, s\'il vous plaît', null, 'lah-dee-SYOHN, seel voo PLEH'],
                ['Je suis végétarien / végétarienne', null, 'zhuh swee vay-zhay-tah-RYAN / -RYEN'],
                ['De l\'eau, s\'il vous plaît', null, 'duh LOH, seel voo PLEH'],
                ['Où est la gare ?', null, 'oo eh lah GAR'],
                ['Où sont les toilettes ?', null, 'oo sohn lay twah-LET'],
                ['C\'est loin d\'ici ?', null, 'seh LWAN dee-SEE'],
                ['Emmenez-moi à cette adresse, s\'il vous plaît', null, 'ahm-NAY mwah ah set ah-DRESS, seel voo PLEH'],
                ['Au secours !', null, 'oh suh-KOOR'],
                ['Appelez la police !', null, 'ah-PLAY lah poh-LEES'],
                ['J\'ai besoin d\'un médecin', null, 'zhay buh-ZWAN dun mayd-SAN'],
                ['Où est l\'hôpital ?', null, 'oo eh loh-pee-TAHL'],
            ]],
            'de' => ['name' => 'German', 'phrases' => [
                ['Hallo', null, 'HAH-loh'],
                ['Danke', null, 'DAHN-kuh'],
                ['Entschuldigung', null, 'ent-SHOOL-dee-goong'],
                ['Auf Wiedersehen', null, 'owf VEE-der-zay-en'],
                ['Die Speisekarte, bitte', null, 'dee SHPY-zuh-kar-tuh, BIT-tuh'],
                ['Die Rechnung, bitte', null, 'dee REKH-noong, BIT-tuh'],
                ['Ich bin Vegetarier / Vegetarierin', null, 'ikh bin veh-geh-TAH-ree-er / -ree-er-in'],
                ['Wasser, bitte', null, 'VAH-ser, BIT-tuh'],
                ['Wo ist der Bahnhof?', null, 'voh ist dehr BAHN-hohf'],
                ['Wo ist die Toilette?', null, 'voh ist dee toy-LET-tuh'],
                ['Ist es weit von hier?', null, 'ist es vyt fon heer'],
                ['Bitte bringen Sie mich zu dieser Adresse', null, 'BIT-tuh BRING-en zee mikh tsoo DEE-zer ah-DRESS-uh'],
                ['Hilfe!', null, 'HIL-fuh'],
                ['Rufen Sie die Polizei!', null, 'ROO-fen zee dee poh-lee-TSY'],
                ['Ich brauche einen Arzt', null, 'ikh BROW-khuh EYE-nen ahrtst'],
                ['Wo ist das Krankenhaus?', null, 'voh ist dahs KRAHN-ken-hows'],
            ]],
            'it' => ['name' => 'Italian', 'phrases' => [
                ['Buongiorno', null, 'bwohn-JOR-noh'],
                ['Grazie', null, 'GRAHT-tsyeh'],
                ['Mi scusi', null, 'mee SKOO-zee'],
                ['Arrivederci', null, 'ah-ree-veh-DEHR-chee'],
                ['Il menù, per favore', null, 'eel meh-NOO, pehr fah-VOH-reh'],
                ['Il conto, per favore', null, 'eel KOHN-toh, pehr fah-VOH-reh'],
                ['Sono vegetariano / vegetariana', null, 'SOH-noh veh-jeh-tah-RYAH-noh / -nah'],
                ['Acqua, per favore', null, 'AHK-kwah, pehr fah-VOH-reh'],
                ['Dov\'è la stazione?', null, 'doh-VEH lah stah-TSYOH-neh'],
                ['Dov\'è il bagno?', null, 'doh-VEH eel BAH-nyoh'],
                ['È lontano da qui?', null, 'eh lohn-TAH-noh dah kwee'],
                ['Mi porti a questo indirizzo, per favore', null, 'mee POR-tee ah KWEH-stoh een-dee-REET-tsoh, pehr fah-VOH-reh'],
                ['Aiuto!', null, 'ah-YOO-toh'],
                ['Chiami la polizia!', null, 'KYAH-mee lah poh-lee-TSEE-ah'],
                ['Ho bisogno di un medico', null, 'oh bee-ZOH-nyoh dee oon MEH-dee-koh'],
                ['Dov\'è l\'ospedale?', null, 'doh-VEH loh-speh-DAH-leh'],
            ]],
            'id' => ['name' => 'Indonesian', 'phrases' => [
                ['Halo', null, 'HAH-loh'],
                ['Terima kasih', null, 'teh-REE-mah KAH-see'],
                ['Permisi / Maaf', null, 'per-MEE-see / MAH-ahf'],
                ['Sampai jumpa', null, 'SAHM-pai JOOM-pah'],
                ['Boleh lihat menunya?', null, 'BOH-leh LEE-haht meh-NOO-nyah'],
                ['Minta bonnya, ya', null, 'MEEN-tah BON-nyah, yah'],
                ['Saya vegetarian', null, 'SAH-yah veh-geh-tah-ree-AHN'],
                ['Minta air putih', null, 'MEEN-tah AH-eer POO-tee'],
                ['Di mana stasiun kereta?', null, 'dee MAH-nah stah-see-OON keh-REH-tah'],
                ['Di mana toilet?', null, 'dee MAH-nah toy-LET'],
                ['Apakah jauh dari sini?', null, 'ah-PAH-kah JAH-ooh DAH-ree SEE-nee'],
                ['Tolong antar saya ke alamat ini', null, 'TOH-long AHN-tar SAH-yah keh ah-LAH-mat EE-nee'],
                ['Tolong!', null, 'TOH-long'],
                ['Panggil polisi!', null, 'PAHNG-gil poh-LEE-see'],
                ['Saya perlu dokter', null, 'SAH-yah per-LOO DOK-ter'],
                ['Di mana rumah sakit?', null, 'dee MAH-nah ROO-mah SAH-kit'],
            ]],
            'ar' => ['name' => 'Arabic', 'phrases' => [
                ['مرحبا', 'marhaban', 'MAR-ha-ban'],
                ['شكرا', 'shukran', 'SHOO-kran'],
                ['لو سمحت', 'law samaht', 'law SA-maht'],
                ['مع السلامة', 'ma\'a as-salama', 'MA-a sa-LAA-ma'],
                ['القائمة من فضلك', 'al-qa\'ima min fadlak', 'al-KAA-ee-ma min FAD-lak'],
                ['الحساب من فضلك', 'al-hisab min fadlak', 'al-hee-SAAB min FAD-lak'],
                ['أنا نباتي', 'ana nabati', 'AH-na na-BAA-tee'],
                ['ماء من فضلك', 'ma\' min fadlak', 'MAA min FAD-lak'],
                ['أين محطة القطار؟', 'ayna mahattat al-qitar?', 'AY-na ma-HAT-tat al-qee-TAAR'],
                ['أين الحمام؟', 'ayna al-hammam?', 'AY-na al-ham-MAAM'],
                ['هل هو بعيد من هنا؟', 'hal huwa ba\'id min huna?', 'hal HOO-wa ba-EED min HOO-na'],
                ['خذني إلى هذا العنوان من فضلك', 'khudhni ila hadha al-\'unwan min fadlak', 'KHUTH-nee EE-la HAA-tha al-oon-WAAN min FAD-lak'],
                ['النجدة!', 'an-najda!', 'an-NAJ-da'],
                ['اتصل بالشرطة!', 'ittasil bish-shurta!', 'it-TA-sil bish-SHOOR-ta'],
                ['أحتاج إلى طبيب', 'ahtaju ila tabib', 'ah-TAA-joo EE-la ta-BEEB'],
                ['أين المستشفى؟', 'ayna al-mustashfa?', 'AY-na al-moos-TASH-fa'],
            ]],
            'zh' => ['name' => 'Mandarin Chinese', 'phrases' => [
                ['你好', 'nǐ hǎo', 'nee how'],
                ['谢谢', 'xièxie', 'shyeh-shyeh'],
                ['不好意思', 'bù hǎoyìsi', 'boo how-ee-suh'],
                ['再见', 'zàijiàn', 'dzai-jyen'],
                ['请给我菜单', 'qǐng gěi wǒ càidān', 'ching gay woh tsai-dahn'],
                ['请买单', 'qǐng mǎidān', 'ching my-dahn'],
                ['我吃素', 'wǒ chī sù', 'woh chr soo'],
                ['请给我一杯水', 'qǐng gěi wǒ yì bēi shuǐ', 'ching gay woh ee bay shway'],
                ['火车站在哪里？', 'huǒchēzhàn zài nǎlǐ?', 'hwoh-chuh-jahn dzai nah-lee'],
                ['洗手间在哪里？', 'xǐshǒujiān zài nǎlǐ?', 'shee-shoh-jyen dzai nah-lee'],
                ['离这里远吗？', 'lí zhèlǐ yuǎn ma?', 'lee juh-lee ywen mah'],
                ['请带我去这个地址', 'qǐng dài wǒ qù zhège dìzhǐ', 'ching dai woh chyoo juh-guh dee-jr'],
                ['救命！', 'jiùmìng!', 'jyoh-ming'],
                ['请叫警察！', 'qǐng jiào jǐngchá!', 'ching jyow jing-chah'],
                ['我需要医生', 'wǒ xūyào yīshēng', 'woh shyoo-yow ee-shung'],
                ['医院在哪里？', 'yīyuàn zài nǎlǐ?', 'ee-ywen dzai nah-lee'],
            ]],
            'ja' => ['name' => 'Japanese', 'phrases' => [
                ['こんにちは', 'konnichiwa', 'kohn-nee-chee-wah'],
                ['ありがとうございます', 'arigatō gozaimasu', 'ah-ree-GAH-toh goh-ZAI-mahs'],
                ['すみません', 'sumimasen', 'soo-mee-mah-sen'],
                ['さようなら', 'sayōnara', 'sah-YOH-nah-rah'],
                ['メニューをお願いします', 'menyū o onegaishimasu', 'MEH-nyoo oh oh-neh-gai-shee-mahs'],
                ['お会計をお願いします', 'okaikei o onegaishimasu', 'oh-kai-kay oh oh-neh-gai-shee-mahs'],
                ['私はベジタリアンです', 'watashi wa bejitarian desu', 'wah-tah-shee wah beh-jee-tah-ree-ahn dess'],
                ['お水をお願いします', 'omizu o onegaishimasu', 'oh-mee-zoo oh oh-neh-gai-shee-mahs'],
                ['駅はどこですか？', 'eki wa doko desu ka?', 'EH-kee wah DOH-koh dess kah'],
                ['トイレはどこですか？', 'toire wa doko desu ka?', 'TOY-reh wah DOH-koh dess kah'],
                ['ここから遠いですか？', 'koko kara tōi desu ka?', 'koh-koh kah-rah TOH-ee dess kah'],
                ['この住所までお願いします', 'kono jūsho made onegaishimasu', 'koh-noh JOO-shoh mah-deh oh-neh-gai-shee-mahs'],
                ['助けて！', 'tasukete!', 'tah-soo-keh-teh'],
                ['警察を呼んでください！', 'keisatsu o yonde kudasai!', 'KAY-sah-tsoo oh YOHN-deh koo-dah-sai'],
                ['医者が必要です', 'isha ga hitsuyō desu', 'ee-shah gah hee-tsoo-YOH dess'],
                ['病院はどこですか？', 'byōin wa doko desu ka?', 'BYOH-een wah DOH-koh dess kah'],
            ]],
            'th' => ['name' => 'Thai', 'phrases' => [
                ['สวัสดี ครับ/ค่ะ', 'sawatdee khrap/kha', 'sah-waht-DEE krahp/kah'],
                ['ขอบคุณ ครับ/ค่ะ', 'khop khun khrap/kha', 'kawp-KOON krahp/kah'],
                ['ขอโทษ ครับ/ค่ะ', 'kho thot khrap/kha', 'kaw-TOHT krahp/kah'],
                ['ลาก่อน', 'la kon', 'lah-GAWN'],
                ['ขอเมนูหน่อย', 'kho menu noi', 'kaw meh-noo noy'],
                ['เช็คบิลด้วย', 'chek bin duai', 'chek bin DOO-ay'],
                ['ฉันกินมังสวิรัติ', 'chan kin mangsawirat', 'chahn gin mahng-sah-wi-RAHT'],
                ['ขอน้ำเปล่าหน่อย', 'kho nam plao noi', 'kaw nahm plow noy'],
                ['สถานีรถไฟอยู่ที่ไหน', 'sathani rot fai yu thi nai', 'sah-tah-NEE rot-fai yoo tee-NAI'],
                ['ห้องน้ำอยู่ที่ไหน', 'hong nam yu thi nai', 'hawng-NAHM yoo tee-NAI'],
                ['ไกลจากที่นี่ไหม', 'klai chak thi ni mai', 'glai jahk tee-NEE mai'],
                ['ไปที่อยู่นี้ ครับ/ค่ะ', 'pai thi yu ni khrap/kha', 'pai tee-yoo NEE krahp/kah'],
                ['ช่วยด้วย!', 'chuai duai!', 'CHOO-ay DOO-ay'],
                ['เรียกตำรวจ!', 'riak tamruat!', 'ree-ak tahm-ROO-at'],
                ['ฉันต้องการหมอ', 'chan tongkan mo', 'chahn tawng-gahn MAW'],
                ['โรงพยาบาลอยู่ที่ไหน', 'rong phayaban yu thi nai', 'rohng pah-yah-BAHN yoo tee-NAI'],
            ]],
            'tr' => ['name' => 'Turkish', 'phrases' => [
                ['Merhaba', null, 'mehr-HAH-bah'],
                ['Teşekkür ederim', null, 'teh-shek-KÜR eh-deh-reem'],
                ['Affedersiniz', null, 'ahf-feh-DEHR-see-neez'],
                ['Hoşça kalın', null, 'HOSH-chah kah-luhn'],
                ['Menü, lütfen', null, 'meh-NÜ, LÜT-fen'],
                ['Hesap, lütfen', null, 'heh-SAHP, LÜT-fen'],
                ['Ben vejetaryenim', null, 'ben veh-zheh-tar-YEH-neem'],
                ['Su, lütfen', null, 'soo, LÜT-fen'],
                ['Tren istasyonu nerede?', null, 'tren ees-tahs-YOH-noo NEH-reh-deh'],
                ['Tuvalet nerede?', null, 'too-vah-LET NEH-reh-deh'],
                ['Buradan uzak mı?', null, 'boo-rah-DAHN oo-ZAHK muh'],
                ['Lütfen beni bu adrese götürün', null, 'LÜT-fen beh-NEE boo ahd-reh-SEH gö-tü-RÜN'],
                ['İmdat!', null, 'eem-DAHT'],
                ['Polisi arayın!', null, 'poh-lee-SEE ah-rah-YUHN'],
                ['Doktora ihtiyacım var', null, 'dohk-toh-RAH eeh-tee-yah-JUHM vahr'],
                ['Hastane nerede?', null, 'hahs-tah-NEH NEH-reh-deh'],
            ]],
        ];
    }

    /**
     * Country names match the destinations table, so a destination finds its tips.
     *
     * @return list<array{0: string, 1: string, 2: array<string, array{0: string, 1: string}>}>
     */
    private function tips(): array
    {
        return [
            ['Japan', 'ja', [
                'tipping' => ['Tipping is not expected', 'Good service is already included and a tip can cause confusion. Some ryokan accept a small envelope (kokorozuke), but it is optional.'],
                'dress_code' => ['Shoes off, tattoos covered', 'Take shoes off in homes, ryokan, temple halls and some restaurants, so wear clean socks. Many onsen refuse visible tattoos; cover them or look for tattoo-friendly baths.'],
                'customs' => ['Quiet trains and careful chopsticks', 'Keep your phone on silent and avoid calls on trains. Queue on platform markings. Never stand chopsticks upright in rice or pass food chopstick to chopstick.'],
                'business_hours' => ['Shops 10:00–20:00, museums often shut Mondays', 'Most shops open 10:00–20:00 and convenience stores 24 hours. Lunch is 11:30–14:00, dinner from 17:30. Trains run roughly 05:00 to midnight.'],
            ]],
            ['Thailand', 'th', [
                'tipping' => ['Small tips are appreciated', 'Round up taxi fares and leave 20–50 THB at casual restaurants. Upscale places often add a 10% service charge. Tip massage therapists 50–100 THB.'],
                'dress_code' => ['Cover shoulders and knees at temples', 'The Grand Palace and temples enforce covered shoulders and knees, with no see-through clothes. Remove shoes before entering temple buildings and homes.'],
                'customs' => ['Respect the monarchy and the head', 'Criticising the royal family is a serious crime. Do not touch anyone\'s head or point your feet at people or Buddha images. Women should not touch monks.'],
                'business_hours' => ['Malls till 22:00, alcohol sales restricted', 'Malls open about 10:00–22:00 and street food runs into the night. Alcohol is not sold 14:00–17:00, after midnight, or on Buddhist holidays.'],
            ]],
            ['Turkey', 'tr', [
                'tipping' => ['Leave 5–10% in cash', 'Leave 5–10% in restaurants, in cash even when paying by card. Round up taxis and tip hamam attendants and tour guides.'],
                'dress_code' => ['Cover up in mosques', 'Women cover hair, shoulders and knees (scarves are often lent at the door); men wear long trousers. Shoes come off inside.'],
                'customs' => ['Accept the tea, bargain at the bazaar', 'Refusing offered tea can seem rude. Haggling is expected in bazaars but not in shops with fixed prices. Avoid showing the soles of your feet.'],
                'business_hours' => ['Grand Bazaar closed on Sundays', 'Shops open about 09:00–19:00, later in tourist areas. The Grand Bazaar is closed on Sundays. Mosques close to visitors during prayer times, especially Friday midday.'],
            ]],
            ['France', 'fr', [
                'tipping' => ['Service is included', 'Bills include service (service compris). Leaving small change or 5–10% for great service is a kind gesture, not an obligation.'],
                'dress_code' => ['Smart-casual in cities', 'Smart-casual is the norm. Cover your shoulders in churches; some upscale restaurants frown on sportswear.'],
                'customs' => ['Always say bonjour first', 'Greet with "Bonjour, madame / monsieur" when entering a shop or starting a conversation. Keep voices low in restaurants.'],
                'business_hours' => ['Many shops close on Sunday', 'Shops open about 10:00–19:00 Monday to Saturday. Lunch is served 12:00–14:00 and dinner from 19:30. The Louvre is closed on Tuesdays.'],
            ]],
            ['Italy', 'it', [
                'tipping' => ['A cover charge, not a tip', 'Many bills include a coperto (cover charge). Tipping is optional; rounding up or €1–2 per person is enough for good service.'],
                'dress_code' => ['Shoulders and knees covered in churches', 'St Peter\'s, the Vatican Museums and most churches enforce covered shoulders and knees, and staff do turn people away.'],
                'customs' => ['No sitting on monuments', 'Sitting on the Spanish Steps or wading in fountains can get you fined. Validate regional train tickets before boarding.'],
                'business_hours' => ['Afternoon break in smaller towns', 'Shops often open 09:30–13:00 and 15:30–19:30, though city centres stay open all day. Dinner starts around 19:30. The Vatican Museums close on most Sundays.'],
            ]],
            ['Indonesia', 'id', [
                'tipping' => ['Service is often already on the bill', 'Many restaurants in Bali add 10% service and tax (shown as "++" on menus). Otherwise tipping is optional: round up for drivers and leave a small note for guides and spa staff.'],
                'dress_code' => ['Wear a sarong at temples', 'Balinese temples require a sarong and sash and covered shoulders; many lend them at the gate. Away from the beach, dress modestly, especially in Java.'],
                'customs' => ['Use your right hand', 'Give and receive things with your right hand, do not touch anyone\'s head, and take shoes off before entering a home. On Nyepi, the Balinese Day of Silence, the whole island shuts down for a day, including the airport.'],
                'business_hours' => ['Tourist shops open late', 'Shops in tourist areas open about 10:00–21:00. Banks open Monday to Friday, roughly 08:00–15:00. Many businesses pause for Friday midday prayers.'],
            ]],
            ['Spain', 'es', [
                'tipping' => ['Small change is fine', 'Tipping is not obligatory. Leave small change, or 5–10% for good table service.'],
                'dress_code' => ['No swimwear in town', 'Casual clothes are fine, but cover shoulders in cathedrals. Some cities, such as Barcelona, fine people walking around in swimwear away from the beach.'],
                'customs' => ['Eat late', 'Two kisses on the cheek is a common greeting among friends. Meals are late, so plan for it.'],
                'business_hours' => ['Lunch 14:00, dinner 21:00', 'Small shops may close 14:00–17:00 for siesta. Lunch is 14:00–16:00 and dinner 21:00–23:00. Many shops close on Sunday.'],
            ]],
            ['Germany', 'de', [
                'tipping' => ['Say the total you want to pay', 'Round up or add 5–10%, and tell the server the total you want to pay rather than leaving money on the table.'],
                'dress_code' => ['Practical clothes; saunas are nude', 'Casual, practical clothing is normal. Many saunas are textile-free, so check the rules before you go.'],
                'customs' => ['Be punctual and wait for the green man', 'Punctuality matters. Do not cross on a red pedestrian light. Respect quiet hours (Ruhezeit) at night and on Sundays.'],
                'business_hours' => ['Almost everything closes on Sunday', 'Shops open about 10:00–20:00 Monday to Saturday. On Sundays nearly all shops close except at stations and airports. Carry some cash.'],
            ]],
            ['United Arab Emirates', 'ar', [
                'tipping' => ['10–15% when not included', 'Check for a service charge; if there is none, 10–15% is appreciated. Round up taxi fares.'],
                'dress_code' => ['Modest in public places', 'Cover shoulders and knees in malls, souks and government buildings. Mosques lend abayas. Swimwear only at beaches and pools.'],
                'customs' => ['Be discreet and ask before photos', 'Public displays of affection can be fined. Ask before photographing people. During Ramadan, avoid eating or drinking in public in daylight.'],
                'business_hours' => ['The weekend is Saturday–Sunday', 'Since 2022 the weekend is Saturday and Sunday, with Friday prayers around midday. Malls open 10:00–22:00, later at weekends. Hours change during Ramadan.'],
            ]],
            ['China', 'zh', [
                'tipping' => ['Tipping is not customary', 'Tips are not expected and may be refused. Tour guides and high-end hotels are the exceptions.'],
                'dress_code' => ['Casual, modest at temples', 'Casual clothes are fine; dress modestly at temples and religious sites.'],
                'customs' => ['Use both hands, set up mobile pay', 'Give and receive cards and gifts with both hands. Do not stand chopsticks in rice or gift clocks. Most payments use Alipay or WeChat Pay, so set them up before you arrive.'],
                'business_hours' => ['Shops open late every day', 'Shops often open 10:00–22:00 daily. Offices work Monday to Friday 09:00–17:00 with a lunch break. Golden Week (1–7 October) and Spring Festival are very crowded.'],
            ]],
        ];
    }
}
