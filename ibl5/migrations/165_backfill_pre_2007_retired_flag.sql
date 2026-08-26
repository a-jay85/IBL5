-- 165_backfill_pre_2007_retired_flag.sql
--
-- Backfill `ibl_plr.retired = 1` for players who retired before the league began
-- archiving .ret files. Nothing ever wrote that column until this PR, so they
-- still render without the retired asterisk on the career leaderboards.
--
-- The pid list is a HUMAN-REVIEWED literal from
-- ibl5/scripts/list-retirement-candidates.php, deliberately NOT a computed
-- predicate: 721 players carry retired = 1 while absent from
-- ibl_jsb_retired_players, so any join-based rule would clear 721 correct flags.
-- Names are carried as trailing comments so the cohort stays reviewable.
--
-- Verified read-only against production 2026-08-25, all 234 pids:
--   * all 234 exist in ibl_plr, all at retired = 0 (none already retired, none NULL)
--   * ibl_plr holds zero NULL rows overall, so the generator's `WHERE retired = 0`
--     filter — which cannot see NULL — is hiding nothing
--   * re-running the generator's predicate with the guard widened to
--     (retired IS NULL OR retired = 0) returns these same 234 and nothing else,
--     so the cohort has not drifted since review
--   * all 234 are free agents (teamid = 0); none is on a roster
--
-- Additive only and idempotent: the `retired IS NULL OR retired = 0` guard means
-- a re-run affects 0 rows, and nothing here can set retired back to 0.

UPDATE ibl_plr
   SET retired = 1
 WHERE pid IN (
    3013, -- Essence Carson
    3597, -- John Wallace
    3888, -- T.R. Dunn
    3895, -- Jonny Flynn
    3905, -- Austin Daye
    3908, -- Byron Mullens
    4180, -- Amaury Pasos
    4190, -- Lindsey Hunter
    4195, -- Yotam Halperin
    4196, -- Fernando San Emeterio
    4204, -- Briann January
    4205, -- Geert Hammink
    4209, -- Evers Burns
    4532, -- Sam Dekker
    4534, -- Frank Kaminsky
    4540, -- Stanley Johnson
    4543, -- Dimitrios Agravanis
    4545, -- Jarell Martin
    4546, -- Juan Pablo Vaulet
    4547, -- Andrew Harrison
    4865, -- Sam Merrill
    4872, -- Rory Sparrow
    4881, -- KJ Martin
    4884, -- Kira Lewis Jr
    4885, -- Theo Maledon
    4886, -- Jahmius Ramsey
    4888, -- John Amaechi
    4890, -- Vit Krejci
    4891, -- CJ Elleby
    5298, -- Sani Becirovic
    5299, -- Mike Sweetney
    5303, -- Sasha Pavlovic
    5304, -- Steve Blake
    5313, -- Dahntay Jones
    5314, -- Paccelis Morlende
    5315, -- Zarko Cabarkapa
    5316, -- Travis Hansen
    5317, -- Jerome Beasley
    5674, -- Fran Vázquez
    5686, -- Mile Ilic
    5690, -- Daniel Ewing
    5694, -- Dijon Thompson
    5695, -- Luther Head
    5696, -- Joey Graham
    5697, -- Chris Taft
    5698, -- Antoine Wright
    5699, -- Travis Diener
    5700, -- Rashad McCants
    5702, -- Martynas Andriuskevicius
    5703, -- Mickael Gelabale
    5705, -- Julius Hodge
    5973, -- Keith Closs
    5985, -- Jelani McCoy
    5986, -- Ansu Sesay
    5987, -- Bruno Šundov
    5988, -- Ryan Bowen
    5989, -- Tremaine Fowlkes
    5990, -- Andrae Patterson
    5991, -- Miles Simon
    5992, -- Corey Benjamin
    6374, -- Jordan Bone
    6376, -- Justin Wright-Fo
    6377, -- Romeo Langford
    6378, -- Dylan Windler
    6380, -- Didi Louzada
    6381, -- Jarrell Brantley
    6383, -- Justin James
    4040404, -- 
    2731, -- Sebastian Telfair
    2738, -- Sasha Vujacic
    3018, -- Odyssey Sims
    2733, -- Chris Duhon
    2734, -- Josh Childress
    3008, -- Sean Rooks
    3016, -- Bryant Stith
    3306, -- Chucky Brown
    3592, -- Tiffany Hayes
    3593, -- Carlos Cabezas
    3596, -- Travis Knight
    3604, -- Mark Hendrickson
    635, -- Glen Ritter
    926, -- Len Bias
    2017, -- Elden Campbell
    2021, -- Antonio Davis
    3583, -- Derek Fisher
    3586, -- Jeff McInnis
    3587, -- Jim Paxson
    3590, -- Walter McCarty
    3595, -- Roy Rogers
    3598, -- Shandon Anderson
    3602, -- Todd Fuller
    3907, -- Derrick Brown
    289, -- Kevin Johnson
    615, -- John Havlicek
    1481, -- Lenny Wilkens
    1490, -- Barack Obama
    2016, -- Tyrone Hill
    2447, -- Kara Lawson
    2727, -- Pavel Podkolzin
    3004, -- Allen Leavell
    3009, -- Will Perdue
    3287, -- Haywoode Workman
    3296, -- Kenny Battle
    3302, -- Doug West
    3581, -- Vitaly Potapenko
    3884, -- Rodrigue Beaubois
    3889, -- Patty Mills
    3892, -- Nando de Colo
    3904, -- Dante Cunningham
    3910, -- Victor Claver
    4186, -- Eric Riley
    4191, -- Greg Graham
    637, -- Jeremy Lin
    1233, -- Ben Simmons
    1236, -- Dejounte Murray
    1247, -- Sergei Belov
    1756, -- Nikola Plecas
    2434, -- George Yardley
    2442, -- Shameka Christon
    2450, -- Keyon Dooling
    2999, -- P.J. Brown
    3022, -- Adam Keefe
    3297, -- Michael Ansley
    3594, -- Javonte Green
    3876, -- Kaya Peker
    3885, -- Danny Green
    3886, -- Ruthie Bolton
    3890, -- DeMarre Carroll
    3894, -- Brandon Jennings
    3898, -- Jodie Meeks
    3899, -- Jonas Jerebko
    3903, -- Jeff Pendergraph
    4182, -- Scott Haskin
    4184, -- Chris Whitney
    4185, -- Danuel House
    4189, -- Chris Mills
    4193, -- Hot Rod Hundley
    4194, -- Ryan Arcidiacono
    4197, -- Mike Peplowski
    4198, -- Chucky Atkins
    4199, -- Acie Earl
    4531, -- Rodney McCray
    4542, -- Dennis Hopson
    4544, -- Martynas Pocius
    617, -- Glenn Robinson
    929, -- Paul Millsap
    931, -- Brandon Roy
    933, -- Rajon Rondo
    1231, -- Kresimir Cosic
    1253, -- Pierluigi Marzorati
    1484, -- Chauncey Billups
    1757, -- Mehmet Okur
    2022, -- Gianmarco Pozzeco
    2040, -- Antonija Misura
    2429, -- Theo Papaloukas
    2430, -- Dennis Johnson
    2438, -- Stromile Swift
    2709, -- Rickey Green II
    2715, -- Tony Allen
    2721, -- Anderson Varejao
    2724, -- Kris Humphries
    2743, -- Robert Swift
    3005, -- Ed Macauley
    3294, -- Stacey King
    3298, -- B.J. Armstrong
    3580, -- Samaki Walker
    3865, -- Joe Ingles
    3883, -- Phil Hubbard
    3901, -- Sam Young
    3906, -- Gerald Henderson
    4156, -- Calvin Cambridge
    4173, -- Rodney Rogers
    4177, -- Scott Burrell
    4178, -- Lucious Harris
    4517, -- Alberto Herreros
    4518, -- Cameron Payne
    4533, -- Chris McCullough
    4536, -- Cedi Osman
    4538, -- Jordan Mickey
    4539, -- Justin Anderson
    4855, -- R.J. Hampton
    4860, -- Jordan Nwora
    4864, -- Reggie Perry
    4869, -- Killian Hayes
    4870, -- Nico Mannion
    4871, -- Skylar Mays
    4875, -- Jalen Harris
    4880, -- Saddiq Bey
    4882, -- Saben Lee
    4883, -- Isaiah Joe
    4889, -- Zeke Nnaji
    652, -- Hisashi Mitsui
    934, -- Kyle Lowry
    936, -- Robert Jaworski
    1237, -- Brandon Ingram
    1482, -- Bill Walton
    1485, -- Stephen Jackson
    1759, -- Troy Murphy
    1764, -- Jason Richardson
    1774, -- Eddie Griffin
    2003, -- Dan Issel
    2010, -- Dino Radja
    2437, -- Nino Buscato
    2443, -- Quentin Richardson
    2445, -- Marko Jaric
    2704, -- Emeka Okafor
    2725, -- Jameer Nelson
    2998, -- Oliver Miller
    3001, -- Georgios Printezis
    3006, -- Hubert Davis
    3290, -- Sherman Douglas
    3295, -- J.R. Reid
    3576, -- Othella Harrington
    3577, -- Antoine Walker
    3585, -- Dell Curry
    3866, -- Richie Guerin
    3871, -- James Johnson
    3878, -- Jerome Whitehead
    3897, -- Toney Douglas
    4175, -- Bryon Russell
    4176, -- Calbert Cheaney
    4181, -- Corie Blount
    4510, -- Tyus Jones
    4521, -- Pat Connaughton
    4537, -- Jerian Grant
    4858, -- Ioannis Bourousis
    4866, -- Elijah Hughes
    4867, -- Marissa Coleman
    4868, -- Udoka Azubuike
    4874, -- Isaiah Stewart
    4876, -- Leandro Bolmaro
    4877, -- Josh Green
    4878, -- Marko Simonovic
    5308  -- Marcus Banks
 )
   AND (retired IS NULL OR retired = 0);
