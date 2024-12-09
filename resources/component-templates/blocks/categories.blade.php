<div class="@if($data['top_margin']) pt-16 sm:pt-24 @endif @if($data['bottom_margin']) pb-16 sm:pb-24 @endif bg-gradient-to-tr from-primary-400 to-primary-600">
    <x-container :show="$data['in_container'] ?? true">
        <div class="px-4 sm:flex sm:items-center sm:justify-between sm:px-6 lg:px-8 xl:px-0">
            <h2>
                {{ $data['title'] }}
            </h2>
            <a href="{{ Translation::get('categories-slug', 'slug', 'categories') }}"
               class="hidden text-sm font-semibold text-white hover:underline trans md:block">
                {{ Translation::get('show-all-categories', 'categories', 'Bekijk alle categorieen') }}
                <span aria-hidden="true"> &rarr;</span>
            </a>
        </div>

        <div class="mt-4 flow-root">
            <div class="-my-2">
                <div class="relative box-content h-80 xl:h-auto overflow-x-auto py-2 xl:overflow-visible">
                    <div class="absolute flex space-x-8 px-4 sm:px-6 lg:px-8 xl:relative xl:grid xl:grid-cols-5 xl:gap-8 xl:space-x-0 xl:px-0">
                        @foreach($data['categories'] as $categoryId)
                            @php($category = \Dashed\DashedEcommerceCore\Models\ProductCategory::find($categoryId))
                            <a href="{{ $category->url }}"
                               data-aos="fade-up"
                               data-aos-delay="{{ $loop->iteration * 100 }}"
                               class="relative flex h-80 w-56 flex-col overflow-hidden rounded-lg p-6 hover:opacity-75 xl:w-auto bg-white">
                              <span aria-hidden="true" class="absolute inset-0">
                                  <x-dashed-files::image
                                          class="h-full w-full object-contain object-center"
                                          :mediaId="$category->image"
                                          :alt="$category->name"
                                          :manipulations="[
                                            'widen' => 300,
                                        ]"
                                  />
                              </span>
                                <span aria-hidden="true"
                                      class="absolute inset-x-0 bottom-0 h-2/3 bg-gradient-to-t from-primary-800"></span>
                                <span class="relative mt-auto text-center text-xl font-bold text-white">{{ $category->name }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-6 px-4 md:hidden">
            <a href="{{ Translation::get('categories-slug', 'slug', 'categories') }}"
               class="block text-sm font-semibold text-white hover:underline trans">
                {{ Translation::get('show-all-categories', 'categories', 'Bekijk alle categorieen') }}
                <span aria-hidden="true"> &rarr;</span>
            </a>
        </div>
    </x-container>
</div>
