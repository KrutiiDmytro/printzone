# Джерела зображень товарів

Демо-каталог (`CatalogFixtures`) використовує світлини під вільними ліцензіями.
CC BY-SA і CC BY вимагають зазначення автора — тому цей файл існує.

| Файл | Товар | Автор | Ліцензія | Джерело |
|---|---|---|---|---|
| `product-1.png` | HP 305 Colour Ink Cartridge | MarcoTangerino | Public domain | [Commons](https://commons.wikimedia.org/wiki/File:HP_Inkjet_Tri-color_Print_Cartridge_22.jpg) |
| `product-2.png` | Canon PG-445 Black Cartridge | cartridgesaveimages | CC BY 2.0 | [Flickr](https://www.flickr.com/photos/186820283@N05/49501261986) |
| `product-4.png` | Epson CISS for L3100 | diskdepot.co.uk | CC BY-SA 4.0 | [Commons](https://commons.wikimedia.org/wiki/File:CISS-tanks.jpg) |
| `product-5.png` | HP 85A Laser Cartridge | Raimond Spekking | CC BY-SA 4.0 | [Commons](https://commons.wikimedia.org/wiki/File:HP_117A_-_black_laser_toner_cartridge-2407.jpg) |
| `product-6.png` | Canon 057H High-Yield Toner | FairToner | CC BY-SA 4.0 | [Commons](https://commons.wikimedia.org/wiki/File:Tonerkartusche.jpg) |
| `product-7.png` | Brother DR-2335 Drum Unit | Raimond Spekking | CC BY-SA 4.0 | [Commons](https://commons.wikimedia.org/wiki/File:Xerox_WorkCentre_6605_-_photoconductor_drum-8998.jpg) |
| `product-8.png` | Epson RC-T1BNA Black Ribbon | Namazu-tron | CC BY-SA 3.0 | [Commons](https://commons.wikimedia.org/wiki/File:Ink_ribbon_cartridge.jpg) |

`product-3`, `product-9`, `product-10` (пляшка чорнил Epson і два фотопапери) ще не
замінені: світлин цих категорій під вільною ліцензією у Commons та Openverse немає.

## Обробка

Кожен файл приведено до 600×450 PNG на чистому білому тлі — під `aspect-ratio: 4/3`
+ `object-fit: contain` у картках товару. Тло вирівняно масштабуванням каналів за
власним фоном знімка, тому картки виглядають однорідно. Де було потрібно — кадрування
(один картридж замість двох ракурсів у `product-1`, лінійка прибрана в `product-8`).

Палітра стиснена до 256 кольорів: на студійних знімках (велика площа рівного білого
+ один об'єкт) це непомітно, але дає ~26% від ваги truecolour-PNG. Разом сім файлів
важать ~355 КБ замість ~1,4 МБ — суттєво для сторінки магазину, де їх до 12 на екран.

## Важливо

Зображення показують товар відповідного **типу**, але не точну модель: на світлині
`product-5` — HP 117A, а не 85A. Для навчального проєкту цього достатньо; перед
комерційним запуском потрібні власні знімки або ліцензовані фото конкретних SKU.
