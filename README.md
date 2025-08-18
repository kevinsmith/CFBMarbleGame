# CFB Marble Game
_Simple Rules for a Complex Season_

A web app built in the open.

## The Algorithm
_[From David Burge](https://x.com/iowahawkblog/status/1706341845326876998), with a few clarifications._

Brief primer on the Marble Game algorithm:

1. Every FBS school starts off with 100 marbles, plus 10 bonus marbles for every P4 opponent on their schedule

2. Beat your opponent at your home place or neutral site ([including conference championships](https://x.com/iowahawkblog/status/1827118895842664563?s=46)), take 20% of their marbles; beat your opponent at their home place, take 25% of their marbles

3. Fractional marbles are rounded to nearest whole number. For example, if you beat an opponent with 210 marbles at their place, you would get 25% of their 210, 52.5, which would be rounded up to 53.

4. If you beat an FCS opponent, you get nothing, If you lose to an FCS opponent, they get 25% of your marbles. Because FU coward.

That's it, that's the whole algorithm.

## Local Dev Setup

To run this locally...

1. Add `cfbmarblegame.test` to your [hosts file](https://linuxize.com/post/how-to-edit-your-hosts-file/)
2. Generate locally-trusted development certs with [mkcert](https://github.com/FiloSottile/mkcert)
3. Create a separate Docker Compose project that runs [Traefik](https://traefik.io/traefik) on an [external network](https://docs.docker.com/reference/cli/docker/network/create/) named `traefik`

Once all that's in place, run `docker compose up` to start up the local dev server, then load https://cfbmarblegame.test in your browser.
