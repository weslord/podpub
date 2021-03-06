CREATE TABLE admin (
  id INTEGER PRIMARY KEY UNIQUE NOT NULL,
  email TEXT NOT NULL,
  password TEXT NOT NULL,
  sessiontoken TEXT,
  sessionexpires INTEGER,
  persistenttoken TEXT,
  persistentexpires INTEGER
);
CREATE TABLE seriesinfo (
  id INTEGER PRIMARY KEY UNIQUE NOT NULL,
  title TEXT,
  artist TEXT,
  copyright TEXT,
  url TEXT,
  owner TEXT,
  email TEXT,
  shortdesc TEXT,
  longdesc TEXT,
  imagefile TEXT,
  category TEXT,
  subcategory TEXT,
  explicit TEXT,
  language TEXT,
  seriestype TEXT
);
CREATE TABLE episodes (
  id INTEGER PRIMARY KEY UNIQUE NOT NULL,
  season INTEGER,
  number INTEGER,
  title TEXT,
  artist TEXT,
  shortdesc TEXT,
  longdesc TEXT,
  episodetype TEXT,
  imagefile TEXT,
  mediafile TEXT,
  mediasize INTEGER,
  mediatype TEXT,
  timestamp INTEGER,
  duration INTEGER,
  permalink TEXT UNIQUE,
  guid TEXT UNIQUE
);
