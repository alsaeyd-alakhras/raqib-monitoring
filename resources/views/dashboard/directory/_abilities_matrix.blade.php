@if (($user->super_admin ?? false))
    <div class="alert alert-info mb-0">هذا الحساب super_admin — يتجاوز جميع الصلاحيات.</div>
@else
    <div class="table-responsive text-nowrap">
        <table class="table table-bordered table-hover">
            <thead class="table-light">
                <tr>
                    <th>صلاحيات المستخدم</th>
                    <th colspan="7">التفعيل</th>
                </tr>
            </thead>
            <tbody>
                @php $userAbilities = $selectedAbilities ?? []; @endphp
                @foreach (app('abilities') as $abilities_name => $ability_array)
                    @php
                        $allAbilities = array_map(function ($key) use ($abilities_name) {
                            return $abilities_name . '.' . $key;
                        }, array_keys(array_filter($ability_array, fn ($key) => $key !== 'name', ARRAY_FILTER_USE_KEY)));
                        $isAllChecked = empty(array_diff($allAbilities, $userAbilities));
                    @endphp
                    <tr>
                        <td class="table-light">
                            <input class="form-check-input master-checkbox" type="checkbox"
                                id="master-{{ $abilities_name }}"
                                data-target="ability-group-{{ $abilities_name }}" @checked($isAllChecked)>
                            <label for="master-{{ $abilities_name }}">{{ $ability_array['name'] }}</label>
                        </td>
                        @foreach ($ability_array as $ability_name => $ability)
                            @if ($ability_name != 'name')
                                <td>
                                    <input class="form-check-input ability-group-{{ $abilities_name }} ability-checkbox"
                                        type="checkbox" name="abilities[]"
                                        id="ability-{{ $abilities_name . '.' . $ability_name }}"
                                        value="{{ $abilities_name . '.' . $ability_name }}"
                                        @checked(in_array($abilities_name . '.' . $ability_name, $userAbilities))>
                                    <label class="form-check-label" for="ability-{{ $abilities_name . '.' . $ability_name }}">
                                        {{ $ability }}
                                    </label>
                                </td>
                            @endif
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
