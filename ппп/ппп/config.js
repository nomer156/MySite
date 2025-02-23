module.exports = {
  steam: {
    accountName: process.env.STEAM_ACCOUNT_NAME,
    password: process.env.STEAM_PASSWORD,
    shared_secret: process.env.STEAM_SHARED_SECRET,
    apiKey: "80EC9A68C835465B9807E520BA5923BF",
    domain: "vexio.ru"
  },
  server: {
    port: process.env.PORT || 3000
  }
};