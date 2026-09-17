import { mkdir, writeFile } from 'node:fs/promises';
import { join } from 'node:path';

export async function prepareArtifacts(runId) {
    const directory = join(process.cwd(), 'storage', 'app', 'research', 'playwright', runId);
    await mkdir(directory, { recursive: true });
    return directory;
}

export async function writeJson(directory, filename, value) {
    await writeFile(join(directory, filename), `${JSON.stringify(value, null, 2)}\n`);
}
