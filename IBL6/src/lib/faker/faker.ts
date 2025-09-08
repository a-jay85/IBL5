import { faker } from '@faker-js/faker';
import { type IblPlayer } from '$lib/models/IblPlayer';

type PlayerPos = 'PG' | 'SG' | 'SF' | 'PF' | 'C';

export const createRandomPlayer = (): IblPlayer => {
	return {
		id: faker.string.uuid(),
		cd: faker.date.past().getTime(),
		pos: faker.helpers.arrayElement<PlayerPos>(['PG', 'SG', 'SF', 'PF', 'C']),
		name: faker.person.fullName(),
		min: faker.number.int({ min: 0, max: 48 }),
		fgm: faker.number.int({ min: 0, max: 20 }),
		fga: faker.number.int({ min: 0, max: 20 }),
		ftm: faker.number.int({ min: 0, max: 20 }),
		fta: faker.number.int({ min: 0, max: 20 }),
		'3pm': faker.number.int({ min: 0, max: 20 }),
		'3pa': faker.number.int({ min: 0, max: 20 }),
		pts: faker.number.int({ min: 0, max: 99 }),
		orb: faker.number.int({ min: 0, max: 20 }),
		reb: faker.number.int({ min: 0, max: 20 }),
		ast: faker.number.int({ min: 0, max: 20 }),
		stl: faker.number.int({ min: 0, max: 20 }),
		blk: faker.number.int({ min: 0, max: 20 }),
		tov: faker.number.int({ min: 0, max: 20 }),
		pf: faker.number.int({ min: 0, max: 20 })
	};
};

export const createRandomPlayers = (count: number): IblPlayer[] => {
	return Array.from({ length: count }, () => createRandomPlayer());
};
