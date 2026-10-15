# Tuana Arıcılık

Tuana Arıcılık, arı ve bal ürünleri satan bir vitrin sitesidir. Ziyaretçi sayfaları, üyelik, tek ürünlü sipariş, yönetim paneli ve kargo ile ödeme bağlantıları aynı PHP uygulamasının içindedir. Sepet yoktur: müşteri bir ürünün sayfasından doğrudan siparişe geçer.

Kod GitHub’da durur. Veritabanı, yönetici şifresi ve ödeme anahtarları depoda yoktur. Canlı sunucuya kod `git pull` ile alınır; veri ve gizli ayar sunucuda kalır.

## Ne yapar

- Ana sayfa, klasik sayfalar, blog ve ürün vitrini yönetim panelinden düzenlenir.
- Ürünlerin kategorisi, fiyatı, indirimli fiyatı, stok takibi ve görsel galerisi vardır.
- Üye kaydı, giriş, hesap bilgisi, kargo adresi ve bireysel ya da kurumsal fatura tutulur.
- İl, ilçe, mahalle ve köy listesinden adres seçilir.
- Sipariş tek üründür. Adet 1 ile 99 arasındadır.
- Ödeme yöntemi kredi kartı (PayTR iFrame), havale ya da elden teslim olabilir. Hangi yöntemlerin açık olduğu panelden seçilir.
- Müşterinin ödediği kargo tutarı, panelde tanımlanan kargo firması listesinden gelir. Liste boşsa siparişe kargo tutarı eklenmez.
- Gönderi etiketi Geliver üzerinden ayrıca oluşturulur. Müşteri Geliver tekliflerini görmez.
- Teslim edilmiş bir siparişte, satın alınmış gönderi varsa, müşteri veya yönetici Geliver iade kodu oluşturabilir. Bu kod siparişi iade durumuna çekmez ve stoğu geri koymaz.
- Yönetim panelinde sipariş masası, kargo gönderim sayfası, müşteri düzenleme, panel grafikleri ve içerik editörü vardır.

## Gerekenler

- PHP 8.2 veya üstü
- MariaDB ya da MySQL
- Apache ve `mod_rewrite` (kökteki `.htaccess` adresleri buna bağlıdır)
- PHP eklentileri: `pdo_mysql`, `curl`, `gd` (WebP desteğiyle), `mbstring`, `openssl`, `json`

Görsel yükleme WebP’ye dönüştürür. Sunucuda `imagewebp` yoksa galeri kaydı çalışmaz.

## Kurulum

1. Depoyu web kökünün altına alın.

   ```bash
   git clone https://github.com/ihsanakyildiz/Tuana-Aricilik.git
   ```

2. Boş bir veritabanı oluşturun. Adın örneği `tuana_aricilik`.

3. Ayar dosyasını kopyalayın.

   ```bash
   cp config/app.example.php config/app.php
   ```

   Windows’ta dosyayı aynı klasöre `app.php` adıyla kopyalayın. İçini doldurun:

   - `debug`: yerelde `true`, canlıda `false`
   - `auth_key`: 64 karakterlik rastgele bir dize. Çerez imzası buna bağlıdır. Canlıda yereldeki anahtarın aynısını kullanmayın.
   - `db`: veritabanı sunucusu, portu, adı, kullanıcısı ve şifresi

4. Siteyi tarayıcıda bir kez açın. Tablolar ilk istekte oluşur. Yönetici tablosu boşsa kullanıcı adı `admin` olur ve şifre `storage/initial-admin.txt` dosyasına yazılır. Bu dosya web’den okunamaz. İlk girişte şifre değiştirilir. Dosyayı okuduktan sonra silebilirsiniz. Yönetici zaten varsa yeni şifre üretilmez.

5. Adres listesini yönetim panelinden yükleyin: **Ayarlar → Adres listesi**. Kaynak dosyalar `storage/geo/` altındadır. Liste bir kez içeri alınır.

6. Ürün, sayfa ve menü yoksa panelden ekleyin. Mevcut bir sitenin verisini taşıyorsanız veritabanı yedeğini içe aktarın. `sql/schema.sql` dosyasını canlı veritabanına yüklemeyin. O dosya eski bir parça yedektir ve `blocks`, `sections` ve `settings` tablolarını silip yeniden kurar.

Yerelde XAMPP kullanılıyorsa site adresi `http://localhost/tuanaAricilik/` şeklindedir. Uygulama kendi klasör adını adreslerden çıkarır. Başka bir alt klasöre taşırsanız bağlantılar o klasöre göre kurulur.

## Canlı sunucu ve sonraki güncellemeler

İlk kurulumda kodu klonlayın, sunucuya özel `config/app.php` yazın ve veritabanını ayrıca taşıyın. PayTR bildirim adresi ile Geliver webhook adresi canlı alan adını göstermelidir.

Sonraki kod güncellemesi:

1. Bilgisayarda değişikliği kaydedip GitHub’a gönderin.
2. Sunucudaki proje klasöründe `git pull` çalıştırın.

Sunucuda PHP dosyalarını elle değiştirmeyin. Çekme işlemi çakışır. `config/app.php` depoda olmadığı için `git pull` onu silmez.

GitHub’a girmeyenler:

- `config/app.php`
- Veritabanı (ürün, sipariş, üye, PayTR ve Geliver ayarları)
- `storage/initial-admin.txt`
- Canlıda panelden yüklenip depoya eklenmemiş yeni görseller

Bu yüzden yedek, veritabanı dışa aktarması ile `assets/uploads/` klasörünü de kapsamalıdır.

## Yönetim paneli

Adres: `/admin/`. Giriş `/admin/giris.php`.

| Bölüm | Ne işe yarar |
| --- | --- |
| Panel | Satış, sipariş ve stok özeti |
| Ürünler ve kategoriler | Katalog, fiyat, stok, galeri |
| Siparişler | Sipariş listesi ve sipariş masası |
| Müşteriler | Üyelik, kargo adresi ve fatura düzenleme |
| Karşılama | Ana sayfa kahraman alanı |
| Sayfalar | Klasik sayfalar ve sayfa içeriği |
| Menüler | Üst menü |
| Bölümler ve kartlar | Ana sayfa bölümleri ve yeniden kullanılan kartlar |
| Yazılar | Blog yazıları ve yazı kategorileri |
| Genel ayarlar | Site adı, iletişim, bakım modu |
| Adres listesi | İl, ilçe, mahalle ve köy |
| Kargo | Müşterinin seçtiği firma ve ücret listesi, Geliver ayarı |
| Ödeme | Kart, havale, elden teslim ve PayTR bilgileri |
| Hesap | Yönetici şifresi |

## Ziyaretçi adresleri

| Adres | Sayfa |
| --- | --- |
| `/` | Ana sayfa |
| `/urunler` | Mağaza |
| `/urunler/kategori/{slug}` | Kategori |
| `/urun/{slug}` | Ürün |
| `/siparis?urun={slug}` | Sipariş |
| `/odeme-kart` | PayTR ödeme formu |
| `/odeme-sonuc` | Kart ödemesinden dönüş. Siparişi burada onaylamaz |
| `/tesekkur` | Sipariş alındı |
| `/uye-ol`, `/uye-giris`, `/hesabim` | Üyelik |
| `/siparislerim` | Üyenin siparişleri |
| `/yazi/{slug}` | Yazı |
| `/blog` | Yazı listesi |
| `/{slug}` | Klasik sayfa |
| `/sitemap.xml`, `/robots.txt` | Site haritası ve robot kuralları |
| `/paytr-bildirim` | PayTR bildirim adresi. Oturum açmaz |
| `/geliver-bildirim` | Geliver webhook adresi. Oturum açmaz |
| `/konum.php?ust={id}` | Adres seçim listesinin JSON kaynağı |

`app/`, `config/`, `data/`, `sql/` ve `storage/` tarayıcıdan açılmaz.

## Sipariş

Sipariş numarası `TA` ile başlar. Ödeme ve durum ayrı tutulur.

Durumlar: ödeme bekliyor, ödendi, teslimde ödenecek, hazırlanıyor, kargoda, teslim edildi, iade edildi, iptal.

- Kart ve havale siparişi **ödeme bekliyor** olarak açılır. Stok, stok takibi açıksa bu anda düşer.
- Elden teslim **teslimde ödenecek** olur.
- Kart siparişi yalnızca PayTR bildirimi `success` döndüğünde **ödendi** olur. Müşterinin dönüş sayfası tahsilatı onaylamaz.
- İptal ve mağaza iadesi tutulan stoğu geri koyar. Geliver iade kodu bunu yapmaz.
- Paneldeki ciro, iade edilen tutarı düşer. İptal ve iade siparişleri canlı satış sayısına girmez.

## PayTR

Entegrasyon [PayTR iFrame API](https://dev.paytr.com/) kullanır. Direkt API kullanılmaz. Kart numarası siteye yazılmaz.

Ayarlar **Ayarlar → Ödeme** ekranındadır ve `settings` tablosunda `paytr` anahtarıyla durur. Mağaza numarası, anahtar ve salt boşsa kart siparişi ödenmiş sayılmaz. Test modu varsayılan olarak açıktır. Test modundayken PayTR `127.0.0.1` adresini kabul etmez. Yerel denemede paneldeki test IP alanına dışarıdan görünen genel IP yazılır.

Bildirim adresi panelde gösterilir. Bunu PayTR mağaza panelinde **Destek ve Kurulum → Ayarlar → Bildirim URL** alanına yazın. Aynı sipariş için bildirim birden fazla gelebilir. Sipariş zaten onaylı ya da iptalse yanıt yine `OK` olur.

Yerel site bu bildirimi alamaz. Deneme için sitenin herkese açık bir HTTPS adresi gerekir. Site adresi ayarını tünel adresine çevirmeyin. O ayar ödeme dönüş adreslerini de değiştirir.

Tam ve parçalı iade, kartla tahsil edilmiş siparişin yönetim ekranındadır. Kalan tutar sıfıra inince sipariş **iade edildi** olur ve tutulan stok geri konur. Havale ve elden teslimde PayTR iade formu görünmez.

Hash hesabını ve örnek bildirim gövdesini karşılaştırmak için PayTR’nin [servis test araçları](https://dev.paytr.com/servis-test-araclari) ve [servis yanıt gözlem](https://dev.paytr.com/servis-test-araclari/servis-yanit-gozlem) sayfaları kullanılabilir. Bu sayfalar ödemeyi tamamlamaz ve bildirimi sitenize göndermez.

## Geliver

Geliver, etiketin alındığı kargo katmanıdır. Müşterinin ödediği ücret yine paneldeki kargo listesinden gelir. Sipariş sırasında otomatik gönderi açılmaz.

Ayarlar **Ayarlar → Kargo** ekranındadır: API token, test modu, paket ölçüleri ve gönderici adresi. Ölçüler santimetre ve kilogramdır. Her gönderi bu ortak ölçüleri kullanır. Ürün başına ayrı ölçü yoktur. Token alanı boş bırakılırsa kayıtlı token durur.

Gönderi, siparişin kargo sayfasından açılır. Sıra şöyledir: gönderi oluştur, teklifleri gör, bir teklifi kabul et, etiketi yazdır. Takip numarası yokken gönderi iptal edilebilir. Takip numarası varsa iptal edilmez. Elden teslim, ödeme bekleyen, iptal, iade ve teslim edilmiş siparişte yeni gönderi açılmaz.

Test modu gerçek etiket satın almaz. Test modunda durum düğmesi, Geliver’in test gönderisini bir adım ilerletir. Bu düğme sayfa her açıldığında çalışmaz. Webhook yerelde ulaşmaz. Canlıda adres `https://alanadiniz/geliver-bildirim` olur ve Geliver panelindeki geliştirici araçlarına yazılır.

Teslim edilmiş ve etiketi alınmış siparişte iade kodu, aynı taşıyıcı için oluşturulur. Ayrıntı Geliver belgelerindedir: [docs.geliver.io](https://docs.geliver.io/docs/home).

## İçerik modeli

Üç kayıt türü vardır.

- **Bölüm**, bir sayfadaki sırayı tutar. Ana sayfa bölümleri karşılama, öne çıkanlar, hakkımızda, bant, hizmetler, ürün vitrini, neden biz, faydalar, promosyon, yorumlar ve yazı listesidir. Grid satırı sayfayı kolonlara böler.
- **Kart**, bölümlerin içine yerleştirilen yeniden kullanılan kayıttır. Kart anahtarı tüm kart türlerinde ortaktır.
- **Sayfa ve yazı**, kendi adresinde açılır. Yazıların kategorisi ayrıdır.

Bölümler doluysa kurulum bunları yeniden üretmez. İçerik panelden değişir.

## Klasörler

| Klasör | İçerik |
| --- | --- |
| `admin/` | Yönetim sayfaları |
| `app/` | Sipariş, üyelik, PayTR, Geliver, içerik ve kurulum sınıfları |
| `assets/` | CSS, JavaScript, hazır görseller ve yüklenen görseller |
| `config/` | `app.example.php`. Gerçek `app.php` yalnızca sunucuda durur |
| `data/` | İlk kurulumun kullandığı örnek içerik |
| `partials/` | Sayfa ve panel parçaları |
| `sql/` | Eski şema dökümü. Canlıya içe aktarılmaz |
| `storage/geo/` | Adres içe aktarma dosyaları |
| `storage/` | Web’den kapalıdır. İlk yönetici şifresi burada oluşur |

Kökteki PHP dosyaları ziyaretçi sayfalarıdır. `index.php` ana sayfayı, `sayfa.php` klasik sayfayı açar.

## Güvenlik

- `config/app.php`, PayTR anahtarı, Geliver token’ı ve veritabanı yedeğini depoya eklemeyin.
- Canlıda `debug` kapalı olsun.
- Yönetim çerezinin yolu `/admin` altıdır. Üye çerezi site klasörüne yazılır. İkisi de `HttpOnly` ve `SameSite=Strict` kullanır.
- Yönetim sayfalarında betikler yalnızca sitenin kendi dosyalarından yüklenir. Panele satır içi betik eklemeyin.
- PayTR ve Geliver isteklerinde SSL doğrulaması açık kalır.
