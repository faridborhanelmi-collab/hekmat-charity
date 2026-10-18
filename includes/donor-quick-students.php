                <!-- Quick Direct Student Selection Grid -->
                <div id="quick-students" class="bg-white p-6 md:p-10 rounded-[3rem] border border-gray-100 shadow-sm">
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6 pb-6 border-b border-gray-100">
                        <div>
                            <h3 class="text-xl font-black text-gray-900 flex items-center gap-2">
                                <span class="w-2.5 h-6 bg-teal-600 rounded-full inline-block"></span>
                                <?php echo !empty($sponsored_students) ? 'افزودن فرزند معنوی جدید به جمع تحت حمایت شما (انتخاب مستقیم)' : 'دانش‌آموزان مستعد چشم‌انتظار حامی (انتخاب مستقیم)'; ?>
                            </h3>
                            <p class="text-xs text-gray-500 mt-1 font-medium">
                                <?php echo !empty($sponsored_students) ? 'شما می‌توانید با انتخاب هر یک از نخبگان زیر، سرپرستی آموزشی آنان را نیز به پرونده خود اضافه نمایید.' : 'با انتخاب هر دانش‌پژوه، سرپرستی معنوی او بلافاصله به پرونده شما افزوده می‌شود. جهت شفافیت و اطمینان، می‌توانید کارنامه تحصیلی و سوابق هر دانش‌پژوه را مشاهده فرمایید.'; ?>
                            </p>
                        </div>
                        <a href="campaign.php#students" class="text-xs text-teal-700 hover:text-teal-800 font-bold shrink-0 bg-teal-50 px-3.5 py-2 rounded-xl border border-teal-200 transition-colors">
                            مشاهده تمام پرونده‌ها در پویش حکمت‌یار ←
                        </a>
                    </div>

                    <!-- Search and Filter Bar -->
                    <div class="space-y-4 mb-8">
                        <!-- Live Search Input -->
                        <div class="relative">
                            <input type="text" x-model="searchQuery"
                                placeholder="جستجو بر اساس کد حکمت‌جو (مثلاً ۳۵ یا ۶۱)، رشته یا مقطع تحصیلی..."
                                class="w-full bg-gray-50 border border-gray-200 rounded-2xl px-4 py-3 pr-11 text-xs focus:outline-none focus:border-teal-500 transition-all font-medium">
                            <div class="absolute right-4 top-3.5 text-gray-400">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            </div>
                            <button type="button" x-show="searchQuery" @click="searchQuery = ''" class="absolute left-4 top-3 text-xs text-gray-400 hover:text-gray-600 font-bold" x-cloak>پاک کردن</button>
                        </div>

                        <!-- Filter Tabs -->
                        <div class="flex items-center gap-2 overflow-x-auto pb-2 scrollbar-none text-xs">
                            <button type="button" @click="activeFilter = 'all'"
                                :class="activeFilter === 'all' ? 'bg-teal-700 text-white font-black shadow-md' : 'bg-gray-100 text-gray-600 hover:bg-gray-200 font-bold'"
                                class="px-4 py-2 rounded-xl transition-all shrink-0">
                                همه حکمت‌جویان (<span x-text="countByFilter('all')"></span>)
                            </button>
                            <button type="button" @click="activeFilter = 'talented'"
                                :class="activeFilter === 'talented' ? 'bg-amber-600 text-white font-black shadow-md' : 'bg-amber-50 text-amber-900 hover:bg-amber-100 border border-amber-300 font-bold'"
                                class="px-4 py-2 rounded-xl transition-all shrink-0 flex items-center gap-1.5">
                                <span>🌟</span>
                                <span>نخبگان استعدادیابی HTI (<span x-text="countByFilter('talented')"></span>)</span>
                            </button>
                            <button type="button" @click="activeFilter = 'available'"
                                :class="activeFilter === 'available' ? 'bg-emerald-700 text-white font-black shadow-md' : 'bg-emerald-50 text-emerald-800 hover:bg-emerald-100 border border-emerald-200 font-bold'"
                                class="px-4 py-2 rounded-xl transition-all shrink-0">
                                چشم‌انتظار حامی (<span x-text="countByFilter('available')"></span>)
                            </button>
                            <button type="button" @click="activeFilter = 'sponsored'"
                                :class="activeFilter === 'sponsored' ? 'bg-amber-700 text-white font-black shadow-md' : 'bg-amber-50 text-amber-800 hover:bg-amber-100 border border-amber-200 font-bold'"
                                class="px-4 py-2 rounded-xl transition-all shrink-0">
                                تحت سرپرستی خیّر (<span x-text="countByFilter('sponsored')"></span>)
                            </button>
                            <button type="button" @click="activeFilter = 'female'"
                                :class="activeFilter === 'female' ? 'bg-rose-700 text-white font-black shadow-md' : 'bg-rose-50 text-rose-800 hover:bg-rose-100 border border-rose-200 font-bold'"
                                class="px-4 py-2 rounded-xl transition-all shrink-0">
                                دختران (<span x-text="countByFilter('female')"></span>)
                            </button>
                            <button type="button" @click="activeFilter = 'male'"
                                :class="activeFilter === 'male' ? 'bg-teal-900 text-white font-black shadow-md' : 'bg-teal-50 text-teal-800 hover:bg-teal-100 border border-teal-200 font-bold'"
                                class="px-4 py-2 rounded-xl transition-all shrink-0">
                                پسران (<span x-text="countByFilter('male')"></span>)
                            </button>
                            <button type="button" @click="activeFilter = 'has_report'"
                                :class="activeFilter === 'has_report' ? 'bg-emerald-700 text-white font-black shadow-md' : 'bg-emerald-50 text-emerald-800 hover:bg-emerald-100 border border-emerald-200 font-bold'"
                                class="px-4 py-2 rounded-xl transition-all shrink-0">
                                دارای کارنامه رسمی (<span x-text="countByFilter('has_report')"></span>)
                            </button>
                            <button type="button" @click="activeFilter = 'senior'"
                                :class="activeFilter === 'senior' ? 'bg-gray-800 text-white font-black shadow-md' : 'bg-gray-100 text-gray-600 hover:bg-gray-200 font-bold'"
                                class="px-4 py-2 rounded-xl transition-all shrink-0">
                                متوسطه دوم (<span x-text="countByFilter('senior')"></span>)
                            </button>
                            <button type="button" @click="activeFilter = 'junior'"
                                :class="activeFilter === 'junior' ? 'bg-gray-800 text-white font-black shadow-md' : 'bg-gray-100 text-gray-600 hover:bg-gray-200 font-bold'"
                                class="px-4 py-2 rounded-xl transition-all shrink-0">
                                متوسطه اول (<span x-text="countByFilter('junior')"></span>)
                            </button>
                        </div>
                    </div>

                    <!-- No Results Message -->
                    <template x-if="filteredStudents.length === 0">
                        <div class="p-12 text-center text-gray-400 font-bold text-xs bg-gray-50 rounded-3xl border border-gray-100">
                            دانش‌پژوهی با مشخصات جستجو شده یافت نشد. لطفاً فیلترها را تغییر دهید.
                        </div>
                    </template>

                    <!-- Student Cards Grid (Dignified - Zero Emojis & Zero Cartoon Avatars) -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <template x-for="st in paginatedStudents" :key="st.id">
                            <div class="p-6 rounded-3xl border transition-all flex flex-col justify-between group"
                                 :class="st.is_sponsored ? 'bg-gray-100/90 border-gray-300 opacity-60 grayscale cursor-not-allowed shadow-none' : 'bg-gradient-to-b from-gray-50/60 to-white border-gray-200/90 hover:border-teal-600 hover:shadow-xl hover:shadow-teal-600/10'">
                                <div>
                                    <!-- Card Header -->
                                    <div class="flex items-start justify-between gap-3 mb-4">
                                        <div class="flex items-center gap-3">
                                            <!-- Dignified Code Badge (No Cartoon Robots) -->
                                            <div class="w-13 h-13 rounded-2xl flex flex-col items-center justify-center font-black transition-transform group-hover:scale-105 shadow-sm shrink-0 p-2"
                                                 :class="st.gender === 'دختر' ? 'bg-gradient-to-br from-rose-50 to-pink-100 text-rose-900 border border-rose-200' : 'bg-gradient-to-br from-slate-100 to-teal-100 text-teal-950 border border-teal-200'">
                                                <span class="text-[9px] opacity-75 font-normal">کد</span>
                                                <span class="text-sm font-black font-mono -mt-0.5" x-text="st.code_fa"></span>
                                            </div>
                                            <div>
                                                <h4 class="font-black text-sm text-gray-900" x-text="st.alias"></h4>
                                                <div class="flex flex-wrap items-center gap-1.5 mt-1">
                                                    <!-- Gender Badge -->
                                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-bold"
                                                          :class="st.gender === 'دختر' ? 'bg-rose-50 text-rose-700 border border-rose-200' : 'bg-blue-50 text-blue-700 border border-blue-200'"
                                                          x-text="st.gender"></span>
                                                    <!-- Grade Label -->
                                                    <span class="text-[11px] text-gray-500 font-bold" x-text="st.grade_disp"></span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Report Card / Sponsored Indicator Badge -->
                                        <div class="flex items-center gap-1.5 shrink-0">
                                            <template x-if="st.is_sponsored">
                                                <span class="px-2.5 py-0.5 rounded-md text-[10px] font-black bg-amber-100 text-amber-900 border border-amber-300">
                                                    تحت سرپرستی خیّر
                                                </span>
                                            </template>
                                            <template x-if="!st.is_sponsored && st.has_report">
                                                <span class="px-2 py-0.5 rounded-md text-[9px] font-bold bg-emerald-50 text-emerald-800 border border-emerald-200">
                                                    کارنامه تایید شده
                                                </span>
                                            </template>
                                        </div>
                                    </div>

                                    <!-- Bursary & Talents Info Box -->
                                    <div class="space-y-2 mb-6 text-xs text-gray-600 bg-white p-4 rounded-2xl border border-gray-100">
                                        <div class="flex items-center justify-between pb-2 border-b border-gray-100">
                                            <span class="text-gray-400 text-[11px]">ارزش بورس ماهانه:</span>
                                            <span class="font-black text-teal-800 font-mono text-[11px]">۳,۰۰۰,۰۰۰ تومان</span>
                                        </div>
                                        <div class="text-[11px] text-gray-600 pt-1 line-clamp-2 leading-relaxed">
                                            <strong class="text-gray-800 font-bold">استعداد و علایق:</strong> <span x-text="st.talents"></span>
                                        </div>
                                        <template x-if="st.hti_score">
                                            <div class="mt-2 pt-2 border-t border-teal-100 flex items-center justify-between text-[11px] bg-gradient-to-r from-amber-500/10 via-teal-500/10 to-transparent p-2.5 rounded-xl border border-teal-500/20">
                                                <span class="font-bold text-teal-900 flex items-center gap-1.5">
                                                    <span class="text-amber-500">🌟</span>
                                                    <span>شاخص استعداد (HTI):</span>
                                                </span>
                                                <span class="font-black font-mono text-teal-950" x-text="st.hti_fa + ' از ۱۰۰ (سطح ' + (st.final_grade || 'A') + ')'"></span>
                                            </div>
                                        </template>

                                        <template x-if="st.hermans_grade || st.eq_grade">
                                            <div class="mt-2 pt-2 border-t border-gray-100 flex flex-wrap items-center gap-1.5 text-[10px]">
                                                <template x-if="st.hermans_grade">
                                                    <span class="bg-amber-50 text-amber-900 border border-amber-200 px-2 py-0.5 rounded-lg font-bold">
                                                        🚀 انگیزه: <span x-text="st.hermans_grade"></span>
                                                    </span>
                                                </template>
                                                <template x-if="st.eq_grade">
                                                    <span class="bg-rose-50 text-rose-800 border border-rose-200 px-2 py-0.5 rounded-lg font-bold">
                                                        ❤️ هوش هیجانی: <span x-text="st.eq_grade"></span>
                                                    </span>
                                                </template>
                                            </div>
                                        </template>
                                    </div>
                                </div>

                                <!-- Actions -->
                                <div class="space-y-2 pt-2 border-t border-gray-100">
                                    <button type="button" @click="selectedStudent = st; showStudentModal = true"
                                        class="w-full bg-white hover:bg-teal-50/60 text-teal-900 border border-teal-200 hover:border-teal-400 py-2.5 px-3 rounded-2xl text-xs font-bold transition-all flex items-center justify-center gap-1.5 shadow-sm">
                                        مشاهده پرونده و کارنامه تحصیلی
                                    </button>
                                    
                                    <template x-if="!st.is_sponsored">
                                        <form method="POST" action="" :onsubmit="'return confirm(\'آیا مایلید سرپرستی معنوی \' + st.alias + \' را بر عهده بگیرید؟\')'">
                                            <input type="hidden" name="action" value="quick_sponsor">
                                            <input type="hidden" name="student_id" :value="st.id">
                                            <button type="submit" class="w-full bg-gradient-to-r from-teal-700 to-teal-800 hover:from-teal-600 hover:to-teal-700 text-white font-black py-2.5 px-4 rounded-2xl shadow-md transition-all text-xs flex items-center justify-center gap-2 group-hover:shadow-teal-700/20">
                                                انتخاب به عنوان فرزند معنوی
                                            </button>
                                        </form>
                                    </template>
                                    <template x-if="st.is_sponsored">
                                        <button disabled type="button" class="w-full bg-gray-200 text-gray-500 font-bold py-2.5 px-4 rounded-2xl cursor-not-allowed text-xs flex items-center justify-center gap-1.5 border border-gray-300">
                                            <span>🔒</span>
                                            <span>تحت حمایت خیّر (غیرقابل انتخاب)</span>
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- Pagination / Show More -->
                    <template x-if="filteredStudents.length > displayLimit">
                        <div class="mt-8 text-center">
                            <button type="button" @click="displayLimit += 12"
                                class="px-8 py-3.5 bg-gray-100 hover:bg-gray-200 text-gray-800 font-bold text-xs rounded-2xl transition-all shadow-sm">
                                نمایش موارد بیشتر (<span x-text="filteredStudents.length - displayLimit"></span> حکمت‌جوی دیگر)...
                            </button>
                        </div>
                    </template>
                </div>

                <!-- Student Dossier & Report Card Modal -->
                <div x-show="showStudentModal" x-cloak class="fixed inset-0 z-[130] flex items-center justify-center p-4 bg-black/75 backdrop-blur-sm"
                    @keydown.escape.window="showStudentModal = false">
                    <div class="bg-white rounded-[2.5rem] shadow-2xl max-w-2xl w-full p-6 md:p-8 relative border border-gray-100 max-h-[92vh] overflow-y-auto"
                        @click.away="showStudentModal = false">
                        
                        <!-- Modal Header -->
                        <div class="flex items-center justify-between pb-4 border-b border-gray-100 mb-6">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 rounded-2xl flex flex-col items-center justify-center font-black shadow-sm p-1"
                                     :class="selectedStudent?.gender === 'دختر' ? 'bg-rose-50 text-rose-800 border border-rose-200' : 'bg-teal-50 text-teal-900 border border-teal-200'">
                                    <span class="text-[9px] opacity-75 font-normal">کد</span>
                                    <span class="text-sm font-black -mt-0.5 font-mono" x-text="selectedStudent?.code_fa"></span>
                                </div>
                                <div>
                                    <h3 class="text-lg font-black text-gray-900" x-text="'پرونده تحصیلی ' + (selectedStudent?.alias || '')"></h3>
                                    <div class="flex items-center gap-2 mt-1">
                                        <span class="px-2.5 py-0.5 rounded-md text-xs font-bold"
                                              :class="selectedStudent?.gender === 'دختر' ? 'bg-rose-50 text-rose-700 border border-rose-200' : 'bg-blue-50 text-blue-700 border border-blue-200'"
                                              x-text="selectedStudent?.gender"></span>
                                        <span class="text-xs text-teal-800 font-bold" x-text="selectedStudent?.grade_disp"></span>
                                    </div>
                                </div>
                            </div>
                            <button type="button" @click="showStudentModal = false" class="w-8 h-8 rounded-full bg-gray-100 text-gray-500 hover:text-gray-800 flex items-center justify-center font-bold text-sm">✕</button>
                        </div>

                        <!-- Modal Body -->
                        <div class="space-y-6">
                            <!-- Highlights Grid -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div class="bg-gray-50 p-4 rounded-2xl border border-gray-100">
                                    <span class="text-[11px] text-gray-400 block mb-1">مقطع و وضعیت تحصیلی:</span>
                                    <span class="text-xs font-black text-gray-800" x-text="selectedStudent?.grade_disp"></span>
                                </div>
                                <div class="bg-gray-50 p-4 rounded-2xl border border-gray-100">
                                    <span class="text-[11px] text-gray-400 block mb-1">ارزش بورس آموزشی ماهانه:</span>
                                    <span class="text-xs font-black text-teal-800 font-mono">۳,۰۰۰,۰۰۰ تومان</span>
                                </div>
                            </div>

                            <!-- Talents -->
                            <div class="bg-gray-50 p-4 rounded-2xl border border-gray-100">
                                <span class="text-[11px] text-gray-400 block mb-1.5 font-bold">استعدادها و سوابق علمی:</span>
                                <p class="text-xs text-gray-700 leading-relaxed font-medium" x-text="selectedStudent?.talents"></p>
                            </div>

                            <!-- Dreams -->
                            <div class="bg-gray-50 p-4 rounded-2xl border border-gray-100">
                                <span class="text-[11px] text-gray-400 block mb-1.5 font-bold">آرمان‌ها و اهداف آینده:</span>
                                <p class="text-xs text-gray-700 leading-relaxed font-medium" x-text="selectedStudent?.dreams"></p>
                            </div>

                            <!-- Comprehensive Psychology & Anonymous Interview Assessment -->
                            <template x-if="selectedStudent?.hti_score || selectedStudent?.explanations">
                                <div class="bg-gradient-to-br from-slate-900 via-teal-950 to-slate-950 text-white p-5 rounded-2xl border border-teal-500/40 shadow-xl space-y-4">
                                    <div class="flex items-center justify-between border-b border-teal-500/30 pb-3">
                                        <div class="flex items-center gap-2">
                                            <span class="text-xl">🌟</span>
                                            <div>
                                                <h4 class="text-xs md:text-sm font-black text-amber-300">ارزیابی استعدادیابی و مصاحبه حضوری حکمت</h4>
                                                <span class="text-[10px] text-teal-300">سنجش استاندارد ۴ گانه استعداد، هوش هیجانی، انگیزه و شخصیت</span>
                                            </div>
                                        </div>
                                        <template x-if="selectedStudent?.hti_score">
                                            <div class="text-left">
                                                <span class="text-[9px] text-teal-300 block">شاخص HTI</span>
                                                <span class="text-sm md:text-base font-black font-mono text-white" x-text="selectedStudent?.hti_fa + ' / ۱۰۰'"></span>
                                                <span class="text-[10px] font-bold text-amber-400 block" x-text="'سطح شایستگی ' + (selectedStudent?.final_grade || 'A')"></span>
                                            </div>
                                        </template>
                                    </div>

                                    <!-- Metrics Badges -->
                                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5 text-center text-xs">
                                        <template x-if="selectedStudent?.mi_total">
                                            <div class="bg-slate-950/60 p-2.5 rounded-xl border border-teal-500/20">
                                                <span class="text-[10px] text-gray-400 block mb-0.5">هوش‌های گاردنر</span>
                                                <span class="font-black text-teal-300 font-mono" x-text="selectedStudent?.mi_total + ' / ۴۰۰'"></span>
                                            </div>
                                        </template>
                                        <template x-if="selectedStudent?.hermans_score">
                                            <div class="bg-slate-950/60 p-2.5 rounded-xl border border-teal-500/20">
                                                <span class="text-[10px] text-gray-400 block mb-0.5">انگیزه پیشرفت</span>
                                                <span class="font-black text-amber-300 font-mono" x-text="selectedStudent?.hermans_score + ' / ۱۰۰'"></span>
                                            </div>
                                        </template>
                                        <template x-if="selectedStudent?.eq_total">
                                            <div class="bg-slate-950/60 p-2.5 rounded-xl border border-teal-500/20">
                                                <span class="text-[10px] text-gray-400 block mb-0.5">هوش هیجانی EQ</span>
                                                <span class="font-black text-rose-300 font-mono" x-text="selectedStudent?.eq_total + ' / ۱۶۰'"></span>
                                            </div>
                                        </template>
                                    </div>

                                    <!-- Interview & Personality Assessment -->
                                    <template x-if="selectedStudent?.explanations">
                                        <div class="bg-slate-950/80 p-3.5 rounded-xl border border-teal-500/20 text-right">
                                            <span class="text-[11px] font-bold text-teal-300 block mb-1.5 flex items-center gap-1.5">
                                                <span>🎙️</span>
                                                <span>چکیده ارزیابی مصاحبه و ویژگی‌های شخصیتی حکمت‌جو (گزارش محرمانه حامیان):</span>
                                            </span>
                                            <p class="text-xs text-gray-200 leading-relaxed font-normal whitespace-pre-line" x-text="selectedStudent?.explanations"></p>
                                        </div>
                                    </template>
                                </div>
                            </template>

                            <!-- Official Report Card Section -->
                            <div class="p-5 rounded-2xl border" :class="selectedStudent?.has_report ? 'bg-emerald-50/70 border-emerald-200' : 'bg-gray-50 border-gray-200'">
                                <div class="flex items-center justify-between mb-3">
                                    <h4 class="text-xs font-black text-gray-900">سند کارنامه تحصیلی رسمی</h4>
                                    <template x-if="selectedStudent?.has_report">
                                        <span class="text-[10px] bg-emerald-100 text-emerald-800 px-2 py-0.5 rounded font-bold border border-emerald-300">موجود و تایید شده</span>
                                    </template>
                                    <template x-if="!selectedStudent?.has_report">
                                        <span class="text-[10px] bg-gray-200 text-gray-600 px-2 py-0.5 rounded font-bold">در صف دریافت از مدرسه</span>
                                    </template>
                                </div>

                                <template x-if="selectedStudent?.has_report">
                                    <div class="space-y-3">
                                        <p class="text-[11px] text-emerald-950 leading-relaxed">
                                             کارنامه تحصیلی این دانش‌پژوه در دبیرخانه بنیاد ثبت و اعتبار نمرات آن به تایید معاونت آموزش رسیده است.
                                        </p>
                                        <div>
                                            <a :href="selectedStudent?.report_path" target="_blank"
                                               class="inline-flex items-center gap-2 bg-emerald-700 hover:bg-emerald-800 text-white font-black px-4 py-2.5 rounded-xl text-xs shadow-md transition-all">
                                                مشاهده و دریافت فایل کارنامه رسمی ←
                                            </a>
                                        </div>
                                        <!-- Image Preview if JPG/PNG -->
                                        <template x-if="selectedStudent?.report_path && (selectedStudent?.report_path.endsWith('.jpg') || selectedStudent?.report_path.endsWith('.jpeg') || selectedStudent?.report_path.endsWith('.png'))">
                                            <div class="mt-3 bg-white p-2 rounded-xl border border-emerald-200 text-center">
                                                <img :src="selectedStudent?.report_path" alt="پیش‌نمایش کارنامه" class="max-h-72 w-auto mx-auto rounded-lg shadow-sm">
                                            </div>
                                        </template>
                                    </div>
                                </template>

                                <template x-if="!selectedStudent?.has_report">
                                    <p class="text-[11px] text-gray-500 leading-relaxed">
                                        پرونده این دانش‌پژوه اخیراً به سامانه افزوده شده و نسخه رسمی کارنامه توسط کارشناسان آموزشی بنیاد در حال دریافت و بررسی نهایی است.
                                    </p>
                                </template>
                            </div>

                            <!-- Modal Action: Sponsor Button -->
                            <form method="POST" action="" :onsubmit="'return confirm(\'آیا مایلید سرپرستی معنوی \' + (selectedStudent?.alias || '') + \' را بر عهده بگیرید؟\')'">
                                <input type="hidden" name="action" value="quick_sponsor">
                                <input type="hidden" name="student_id" :value="selectedStudent?.id">
                                <button type="submit" class="w-full bg-gradient-to-r from-teal-700 to-teal-800 hover:from-teal-600 hover:to-teal-700 text-white font-black py-4 px-6 rounded-2xl shadow-xl transition-all text-xs flex items-center justify-center gap-2">
                                    تایید و ثبت سرپرستی معنوی این دانش‌پژوه
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
