<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Create Character') }}
        </h2>
    </x-slot>

    @php
        $racesData = $races->map(fn ($r) => [
            'id' => $r->id,
            'name' => $r->name,
            'cost' => $r->cost,
            'alignments' => $r->allowed_alignments,
            'description' => $r->description,
        ])->values();

        $classesData = $classes->map(fn ($c) => [
            'id' => $c->id,
            'name' => $c->name,
            'alignments' => $c->allowed_alignments,
            'description' => $c->description,
        ])->values();
    @endphp

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900"
                     x-data="{
                        races: {{ $racesData->toJson() }},
                        classes: {{ $classesData->toJson() }},
                        raceId: '{{ old('race_id') }}',
                        classId: '{{ old('character_class_id') }}',
                        alignment: '{{ old('alignment') }}',
                        get race() { return this.races.find(r => r.id == this.raceId) },
                        get klass() { return this.classes.find(c => c.id == this.classId) },
                        get availableClasses() {
                            if (!this.race) return this.classes;
                            return this.classes.filter(c => c.alignments.some(a => this.race.alignments.includes(a)));
                        },
                        isClassAvailable(c) {
                            if (!this.race) return true;
                            return c.alignments.some(a => this.race.alignments.includes(a));
                        },
                        get allowedAlignments() {
                            if (!this.race || !this.klass) return [];
                            return this.race.alignments.filter(a => this.klass.alignments.includes(a));
                        },
                    }"
                     x-init="
                        $watch('raceId', () => {
                            if (classId && !isClassAvailable(klass)) classId = '';
                        });
                        $watch('allowedAlignments', val => {
                            if (val.length === 1) { alignment = val[0]; return; }
                            if (!val.includes(alignment)) alignment = '';
                        });
                     ">

                    <form method="POST" action="{{ route('characters.store') }}" class="space-y-6">
                        @csrf

                        <div>
                            <x-input-label for="name" :value="__('Name')" />
                            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                                          :value="old('name')" required autofocus />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="race_id" :value="__('Race')" />
                            <select id="race_id" name="race_id" x-model="raceId" required
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                <option value="">-- Select race --</option>
                                @foreach ($races as $race)
                                    <option value="{{ $race->id }}">
                                        {{ $race->name }} (cost {{ $race->cost }})
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-gray-500" x-show="race" x-text="race?.description"></p>
                            <x-input-error :messages="$errors->get('race_id')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="character_class_id" :value="__('Class')" />
                            <select id="character_class_id" name="character_class_id" x-model="classId" required
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                <option value="">-- Select class --</option>
                                <template x-for="c in availableClasses" :key="c.id">
                                    <option :value="c.id" x-text="c.name"></option>
                                </template>
                            </select>
                            <p class="mt-1 text-xs text-gray-500" x-show="race && availableClasses.length < classes.length">
                                Some classes are hidden because they don't match <span class="font-semibold" x-text="race?.name"></span>'s alignment.
                            </p>
                            <p class="mt-1 text-xs text-gray-500" x-show="klass" x-text="klass?.description"></p>
                            <x-input-error :messages="$errors->get('character_class_id')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="sphere_id" :value="__('Sphere')" />
                            <select id="sphere_id" name="sphere_id"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                <option value="">-- None --</option>
                                @foreach ($spheres as $sphere)
                                    <option value="{{ $sphere->id }}" @selected(old('sphere_id') == $sphere->id)>
                                        {{ $sphere->name }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('sphere_id')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label :value="__('Alignment')" />
                            <div class="mt-2 flex gap-4" x-show="raceId && classId">
                                <template x-for="opt in ['good', 'neutral', 'evil']" :key="opt">
                                    <label class="inline-flex items-center"
                                           :class="allowedAlignments.includes(opt) ? '' : 'opacity-40 cursor-not-allowed'">
                                        <input type="radio" name="alignment" :value="opt" x-model="alignment"
                                               :disabled="!allowedAlignments.includes(opt)"
                                               class="text-indigo-600 focus:ring-indigo-500">
                                        <span class="ml-2 capitalize" x-text="opt"></span>
                                    </label>
                                </template>
                            </div>
                            <p class="mt-2 text-sm text-gray-500" x-show="!raceId || !classId">
                                Select race and class to see available alignments.
                            </p>
                            <p class="mt-2 text-sm text-indigo-600" x-show="raceId && classId && allowedAlignments.length === 1">
                                Alignment is locked to <span class="font-semibold capitalize" x-text="allowedAlignments[0]"></span> for this race/class.
                            </p>
                            <p class="mt-2 text-sm text-red-600" x-show="raceId && classId && allowedAlignments.length === 0">
                                This race/class combination has no compatible alignment.
                            </p>
                            <x-input-error :messages="$errors->get('alignment')" class="mt-2" />
                        </div>

                        <div class="flex items-center gap-4">
                            <x-primary-button>{{ __('Create Character') }}</x-primary-button>
                            <a href="{{ route('characters.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
