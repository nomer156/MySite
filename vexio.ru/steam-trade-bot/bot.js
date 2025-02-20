/**
 * bot.js – Steam Trade Bot для обработки депозитов/выводов,
 * а также предоставления API для сайта.
 */

const SteamUser = require('steam-user');
const TradeOfferManager = require('steam-tradeoffer-manager');
const SteamCommunity = require('steamcommunity');
const express = require('express');
const bodyParser = require('body-parser');
const fs = require('fs');
const steamTotp = require('steam-totp');

// Загружаем конфигурацию бота
const config = JSON.parse(fs.readFileSync('config_bot.json', 'utf8'));

// Создаем экземпляр SteamUser
const client = new SteamUser();

// Создаем менеджер трейдов
const manager = new TradeOfferManager({
  steam: client,
  domain: config.domain,
  language: 'en',
  apiKey: config.apiKey
});

// Создаем объект SteamCommunity
const community = new SteamCommunity();

// Логинимся с использованием 2FA-кода, сгенерированного через steam-totp
const logOnOptions = {
  accountName: config.accountName,
  password: config.password,
  twoFactorCode: steamTotp.generateAuthCode(config.shared_secret)
};

client.logOn(logOnOptions);

client.on('loggedOn', () => {
  console.log('Bot logged on successfully.');
  client.setPersona(SteamUser.EPersonaState.Online);
});

client.on('error', (err) => {
  console.error('Steam client error:', err);
});

// Когда получаем веб-сессию, передаем cookies в менеджер офферов и community
client.on('webSession', (sessionID, cookies) => {
  manager.setCookies(cookies, (err) => {
    if (err) {
      console.error('Ошибка установки cookies для TradeOfferManager:', err);
      process.exit(1);
    }
    console.log('TradeOfferManager cookies установлены.');
  });
  community.setCookies(cookies);
});

// Обработка входящих офферов – для депозитов от пользователей
manager.on('newOffer', (offer) => {
  console.log('Получен новый оффер. ID оффера:', offer.id);
  // Здесь можно добавить логику для проверки оффера (например, его источник, предметы, стоимость)
  // Пока отклоняем все непредвиденные офферы
  offer.decline((err) => {
    if (err) {
      console.error('Ошибка отклонения оффера:', err);
    } else {
      console.log('Оффер отклонен.');
    }
  });
});

// Создаем Express-сервер для API, через который сайт сможет инициировать трейды
const app = express();
app.use(bodyParser.json());
app.use(bodyParser.urlencoded({ extended: true }));

// Простой endpoint для проверки статуса бота
app.get('/status', (req, res) => {
  res.json({ status: 'ok', personaState: client.myPersonaState });
});

// Endpoint для создания оффера на вывод (бот отправляет предметы пользователю)
// Параметры: userSteamId, items (массив объектов с информацией об item, например, assetid, contextid, appid)
app.post('/offer/withdraw', (req, res) => {
  const { userSteamId, items } = req.body;
  if (!userSteamId || !items || !Array.isArray(items) || items.length === 0) {
    return res.status(400).json({ error: 'Неверные параметры' });
  }
  // Создаем оффер от бота к пользователю
  let offer = manager.createOffer(userSteamId);
  offer.addMyItems(items);
  // Отправляем оффер (не запрашивая ничего от пользователя)
  offer.send((err, status) => {
    if (err) {
      console.error('Ошибка отправки оффера:', err);
      return res.status(500).json({ error: err.message });
    }
    res.json({ status: status, offerId: offer.id });
  });
});

// Простой endpoint для получения инвентаря бота для заданного appid/contextid (например, CS2: appid=730, contextid=2)
app.get('/inventory', (req, res) => {
  const appid = parseInt(req.query.appid) || 730;
  const contextid = parseInt(req.query.contextid) || 2;
  community.getInventoryContents(config.accountId, appid, contextid, true, (err, inventory) => {
    if (err) {
      return res.status(500).json({ error: err.message });
    }
    res.json({ inventory: inventory });
  });
});

// Запускаем Express-сервер на порту 3000 (или указанном PORT)
const PORT = process.env.PORT || 3000;
app.listen(PORT, () => {
  console.log(`Bot API сервер запущен на порту ${PORT}`);
});
