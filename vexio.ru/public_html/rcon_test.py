from valve.rcon import RCON
HOST = "46.174.52.43"
PORT = 27015
PASSWORD = "ciAlweYf28NwXorGsSLs"

with RCON((HOST, PORT), PASSWORD) as rcon:
    print(rcon("say Test from Python RCON!"))
