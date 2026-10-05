// Локальный сервер для демонстрации без установленного PHP/MySQL.
// Для сдачи работы используйте api.php + database.sql, описанные в README.md.
const http = require('http');
const fs = require('fs');
const path = require('path');
const root = __dirname;

const cars = {
  XTA219010G0123456: { title:'LADA Granta', subtitle:'2016 г. · 1.6 · 87 л.с. · МКПП · Передний', color:'Белый', score:38, risk:'Высокий риск', theft:['Не числится','Проверено по учебному реестру.','good'], restrictions:['Есть ограничение','Запрет на регистрационные действия от 12.03.2025.','danger'], pledge:['Не найден','Записей в реестре залогов нет.','good'], owners:['3 владельца','3 регистрации с 2016 года.'], mileage:['186 400 км','Есть скачок пробега на 18 000 км в 2022 году.','danger'], taxi:['Использовался','Запись в демонстрационном реестре такси: 2018–2020.','danger'], accident:['Зарегистрированы','2 ДТП: передний бампер и правая дверь.','danger'], paint:['Есть сведения','Окрас правой передней двери.','danger'] },
  XW8ZZZ3CZEG000111: { title:'Skoda Octavia', subtitle:'2014 г. · 1.8 TSI · 180 л.с. · АКПП · Передний', color:'Серый', score:78, risk:'Требует внимания', theft:['Не числится',null,'good'], restrictions:['Не найдены','Регистрационных запретов нет.','good'], pledge:['Не найден',null,'good'], owners:['2 владельца','Последний владелец с 2021 года.'], mileage:['142 300 км','Пробег последовательно подтверждён 6 записями.','good'], taxi:['Не использовался','Признаков работы в такси нет.','good'], accident:['Не зарегистрированы',null,'good'], paint:['Не обнаружены','Сведений об окрасе и кузовном ремонте нет.','good'] },
  Z94CB41AAGR123456: { title:'Hyundai Solaris', subtitle:'2016 г. · 1.6 · 123 л.с. · АКПП · Передний', color:'Белый', score:52, risk:'Требует внимания', theft:['Не числится',null,'good'], restrictions:['Не найдены',null,'good'], pledge:['Не найден',null,'good'], owners:['4 владельца','Частая смена владельцев.'], mileage:['219 800 км','Показания соответствуют данным техосмотров.','good'], taxi:['Использовался','Учебная запись такси: 2017–2022.','danger'], accident:['Зарегистрированы','1 ДТП: задний бампер, 2023 год.','danger'], paint:['Есть сведения','Окрас заднего бампера.','danger'] },
  WVWZZZ3CZHE000222: { title:'Volkswagen Passat', subtitle:'2017 г. · 1.4 TSI · 150 л.с. · Робот · Передний', color:'Чёрный', score:88, risk:'Низкий риск', theft:['Не числится',null,'good'], restrictions:['Не найдены',null,'good'], pledge:['Не найден',null,'good'], owners:['1 владелец','Владеет с 2018 года.'], mileage:['97 400 км','Пробег подтверждён дилерскими ТО.','good'], taxi:['Не использовался',null,'good'], accident:['Не зарегистрированы',null,'good'], paint:['Не обнаружены',null,'good'] },
  JTMBD33V405012345: { title:'Toyota RAV4', subtitle:'2008 г. · 2.4 · 170 л.с. · АКПП · Полный', color:'Зелёный', score:67, risk:'Требует внимания', theft:['Не числится',null,'good'], restrictions:['Не найдены',null,'good'], pledge:['Не найден',null,'good'], owners:['3 владельца','Последняя смена собственника в 2020 году.'], mileage:['198 700 км','Пробег подтверждён 4 техосмотрами.','good'], taxi:['Нет данных','Реестр не содержит сведений за этот период.','unknown'], accident:['Зарегистрированы','1 ДТП: левое переднее крыло, 2019 год.','danger'], paint:['Есть сведения','Локальный окрас левого крыла.','danger'] },
  SJNFAAJ11U1234567: { title:'Nissan Qashqai', subtitle:'2019 г. · 2.0 · 144 л.с. · Вариатор · Передний', color:'Серебристый', score:82, risk:'Низкий риск', theft:['Не числится',null,'good'], restrictions:['Не найдены',null,'good'], pledge:['Не найден',null,'good'], owners:['2 владельца',null], mileage:['68 300 км','Данные пробега совпадают во всех источниках.','good'], taxi:['Не использовался',null,'good'], accident:['Не зарегистрированы',null,'good'], paint:['Нет данных','Сведения об осмотре кузова отсутствуют.','unknown'] },
};
const extra = [['XTA210990J0000001','LADA Vesta'],['WBA3A51070F000333','BMW 320i'],['X7LHSRGA651234567','Renault Duster'],['KNAGN418BDA000444','Kia Optima'],['VF1BZ0L0541234567','Renault Logan'],['JN1TCNT32U0005555','Nissan X-Trail'],['SALVA2BG8FH000666','Land Rover Range Rover Evoque'],['WAUZZZ8V6GA000777','Audi A3'],['XWEJN811BD0008888','Kia Sportage']];
extra.forEach(([vin, title], i) => cars[vin] = { title, subtitle:'Учебная карточка · подробные данные в MySQL-версии', color:'Не указан', score:70 + i % 15, risk:'Требует внимания', theft:['Не числится',null,'good'], restrictions:['Не найдены',null,'good'], pledge:['Не найден',null,'good'], owners:['Нет данных','Для этой записи информация неполная.','unknown'], mileage:['Нет данных','Для этой записи информация неполная.','unknown'], taxi:['Нет данных','В источниках нет сведений по этому параметру.','unknown'], accident:['Нет данных','В источниках нет сведений по этому параметру.','unknown'], paint:['Нет данных','В источниках нет сведений по этому параметру.','unknown'] });

function group(title, items) { return { title, items: items.map(([label, data]) => ({ label, value:data[0], details:data[1], state:data[2] || 'normal' })) }; }
function report(vin, car) { return { ok:true, vehicle:{ vin, title:car.title, subtitle:car.subtitle, color:car.color, riskScore:car.score, riskLabel:car.risk, updatedAt:'2026-09-14' }, groups:[
  group('Юридическая чистота', [['Угон / розыск',car.theft],['Ограничения ГИБДД',car.restrictions],['Залог',car.pledge],['ПТС',['Оригинал','Учебная запись.','normal']],['Таможенное оформление',['Оформлено','Учебная запись.','normal']]]),
  group('Эксплуатация и история', [['Количество владельцев',car.owners],['Пробег',car.mileage],['Работа в такси',car.taxi],['Каршеринг',['Нет данных','В источниках нет сведений по этому параметру.','unknown']],['Лизинг',['Не использовался','Договоров лизинга не найдено.','good']],['Техосмотр',['Пройден','Учебная запись.','good']]]),
  group('Состояние и события', [['ДТП',car.accident],['Окрасы и ремонт',car.paint],['Страховые расчёты',['Нет данных','В источниках нет сведений по этому параметру.','unknown']],['Продажи на аукционах',['Нет данных','В источниках нет сведений по этому параметру.','unknown']],['Сервисные кампании',['Нет кампаний','Открытых кампаний нет.','good']]])
]}; }
const mime = { '.html':'text/html; charset=utf-8', '.css':'text/css; charset=utf-8', '.js':'application/javascript; charset=utf-8' };
http.createServer((req, res) => {
  const url = new URL(req.url, 'http://localhost');
  if (url.pathname === '/api.php') {
    const vin = (url.searchParams.get('vin') || '').toUpperCase(); const car = cars[vin];
    res.writeHead(car ? 200 : 404, {'Content-Type':'application/json; charset=utf-8'});
    return res.end(JSON.stringify(car ? report(vin, car) : {ok:false, message:'Автомобиль с таким VIN не найден в учебной базе.', vin}));
  }
  const name = url.pathname === '/' ? 'index.html' : path.basename(url.pathname);
  const file = path.join(root, name);
  if (!fs.existsSync(file)) { res.writeHead(404); return res.end('Not found'); }
  res.writeHead(200, {'Content-Type':mime[path.extname(file)] || 'text/plain; charset=utf-8'});
  fs.createReadStream(file).pipe(res);
}).listen(8000, '127.0.0.1', () => console.log('VINscope: http://localhost:8000'));
