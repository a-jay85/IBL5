// Helper function to convert BigInt to string
export function serializeBigInt(obj: unknown): unknown {
	if (obj === null || obj === undefined) {
		return obj;
	}

	if (typeof obj === 'bigint') {
		return obj.toString();
	}

	if (Array.isArray(obj)) {
		return obj.map(serializeBigInt);
	}

	if (typeof obj === 'object') {
		const serialized: Record<string, unknown> = {};
		for (const key in obj) {
			if (Object.prototype.hasOwnProperty.call(obj, key)) {
				serialized[key] = serializeBigInt((obj as Record<string, unknown>)[key]);
			}
		}
		return serialized;
	}

	return obj;
}
