<?php

namespace App\Support;

use App\Models\Account;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Support\Collection;

class ChartOfAccountsSynchronizer
{
    private const BASE_DEFINITIONS = [
        ['code' => '1', 'name' => 'الأصول', 'name_ar' => 'الأصول', 'account_type' => 'asset', 'description' => null, 'allows_direct_transactions' => false, 'match_codes' => ['1000'], 'match_fragments' => ['الأصول', 'Assets']],
        ['code' => '11', 'name' => 'أصول متداولة', 'name_ar' => 'أصول متداولة', 'account_type' => 'asset', 'parent' => '1', 'description' => null, 'allows_direct_transactions' => false, 'match_codes' => ['1.10', '1010'], 'match_fragments' => ['أصول متداولة', 'الأصول المتداولة', 'Current Assets']],
        ['code' => '1101', 'name' => 'النقد ومايعادله', 'name_ar' => 'النقد ومايعادله', 'account_type' => 'asset', 'parent' => '11', 'description' => 'النقدية وما في حكمها (في الخزينة والعهد)', 'allows_direct_transactions' => false, 'match_codes' => ['1.1', '1100', '1101'], 'match_fragments' => ['النقد ومايعادله', 'النقدية وما في حكمها', 'الصندوق', 'Cash']],
        ['code' => '110101', 'name' => 'النقدية في الخزينة', 'name_ar' => 'النقدية في الخزينة', 'account_type' => 'asset', 'parent' => '1101', 'description' => 'النقدية في الخزينة', 'allows_direct_transactions' => true, 'match_codes' => ['1010'], 'match_fragments' => ['النقدية في الخزينة', 'الصندوق', 'Cash']],
        ['code' => '110102', 'name' => 'العهد النقدية', 'name_ar' => 'العهد النقدية', 'account_type' => 'asset', 'parent' => '1101', 'description' => 'العهد النقدية للموظفين بشكل مؤقت أو دائم لدفع مصروفات المنشأة', 'allows_direct_transactions' => true, 'match_fragments' => ['العهد النقدية', 'عهد نقدية']],
        ['code' => '1102', 'name' => 'النقدية في البنك', 'name_ar' => 'النقدية في البنك', 'account_type' => 'asset', 'parent' => '11', 'description' => 'النقدية في البنوك', 'allows_direct_transactions' => false, 'match_codes' => ['1102', '1020'], 'match_fragments' => ['النقدية في البنك', 'البنك', 'Bank']],
        ['code' => '110201', 'name' => 'حساب البنك الجاري - اسم البنك', 'name_ar' => 'حساب البنك الجاري - اسم البنك', 'account_type' => 'asset', 'parent' => '1102', 'description' => 'حساب البنك الجاري - اسم البنك', 'allows_direct_transactions' => true, 'match_codes' => ['1.2'], 'match_fragments' => ['حساب البنك الجاري', 'الحساب البنكي', 'Bank Account', 'Bank']],
        ['code' => '1103', 'name' => 'المدينون', 'name_ar' => 'المدينون', 'account_type' => 'asset', 'parent' => '11', 'description' => 'مبالغ مستحقة على حساب العملاء (بالأجل)', 'allows_direct_transactions' => false, 'match_codes' => ['1.3', '1200'], 'match_fragments' => ['المدينون', 'العملاء', 'ذمم مدينة', 'Receivable']],
        ['code' => '1104', 'name' => 'مصروفات مقدمة', 'name_ar' => 'مصروفات مقدمة', 'account_type' => 'asset', 'parent' => '11', 'description' => 'مصروف مدفوع مقدماً مثل التأمين وسلف الموظفين وإيجار المكتب', 'allows_direct_transactions' => false, 'match_codes' => ['1.6', '1500'], 'match_fragments' => ['مصروفات مقدمة', 'مصروفات مدفوعة مقدماً', 'Prepaid Expenses']],
        ['code' => '110401', 'name' => 'تأمين طبي مقدم', 'name_ar' => 'تأمين طبي مقدم', 'account_type' => 'asset', 'parent' => '1104', 'description' => 'تأمين طبي مدفوع مقدماً يتم إطفاء مايخص السنة المالية إلى مصروف', 'allows_direct_transactions' => false],
        ['code' => '110402', 'name' => 'إيجار مقدم', 'name_ar' => 'إيجار مقدم', 'account_type' => 'asset', 'parent' => '1104', 'description' => 'إيجار مدفوع مقدماً يتم إطفاء مايخص السنة المالية إلى مصروف', 'allows_direct_transactions' => false],
        ['code' => '1105', 'name' => 'مدفوعات مقدمة للموظفين', 'name_ar' => 'مدفوعات مقدمة للموظفين', 'account_type' => 'asset', 'parent' => '11', 'description' => 'سلف الموظفين يلتزم الموظف بسدادها حسب المتفق عليه', 'allows_direct_transactions' => false],
        ['code' => '1106', 'name' => 'المخزون', 'name_ar' => 'المخزون', 'account_type' => 'asset', 'parent' => '11', 'description' => 'المخزون ويشمل المواد أولية وتامة الصنع', 'allows_direct_transactions' => false, 'match_codes' => ['1.4'], 'match_fragments' => ['المخزون', 'Inventory']],
        ['code' => '12', 'name' => 'أصول غير متداولة', 'name_ar' => 'أصول غير متداولة', 'account_type' => 'asset', 'parent' => '1', 'description' => null, 'allows_direct_transactions' => false, 'match_codes' => ['1.20'], 'match_fragments' => ['أصول غير متداولة', 'الأصول غير المتداولة', 'Non-current Assets']],
        ['code' => '1201', 'name' => 'عقارات وآلات ومعدات', 'name_ar' => 'عقارات وآلات ومعدات', 'account_type' => 'asset', 'parent' => '12', 'description' => 'الممتلكات والآلات والمعدات', 'allows_direct_transactions' => false, 'match_codes' => ['1.7', '1300'], 'match_fragments' => ['عقارات وآلات ومعدات', 'الأصول الثابتة', 'Fixed Assets']],
        ['code' => '120101', 'name' => 'الأراضي', 'name_ar' => 'الأراضي', 'account_type' => 'asset', 'parent' => '1201', 'description' => 'الأراضي الممتلكة من قبل المنشأة', 'allows_direct_transactions' => false],
        ['code' => '120102', 'name' => 'المباني', 'name_ar' => 'المباني', 'account_type' => 'asset', 'parent' => '1201', 'description' => 'المباني التي تستخدم في عمليات الشركة مثل المخازن والمكاتب والمصانع والمستودعات', 'allows_direct_transactions' => false],
        ['code' => '120103', 'name' => 'المعدات', 'name_ar' => 'المعدات', 'account_type' => 'asset', 'parent' => '1201', 'description' => 'المعدات المستخدمة في عمليات التشغيل', 'allows_direct_transactions' => false, 'match_codes' => ['1310']],
        ['code' => '120104', 'name' => 'أجهزة مكتبية وطابعات', 'name_ar' => 'أجهزة مكتبية وطابعات', 'account_type' => 'asset', 'parent' => '1201', 'description' => 'أجهزة مكتبية مثل الحاسب الآلي ، الجهاز المحمول وطابعات', 'allows_direct_transactions' => false, 'match_codes' => ['1320']],
        ['code' => '1202', 'name' => 'الأصول غير الملموسة', 'name_ar' => 'الأصول غير الملموسة', 'account_type' => 'asset', 'parent' => '12', 'description' => 'الأصول غير الملموسة مثل حق الشهرة وبراءة الاختراع وحقوق النسخ والعلامات التجارية', 'allows_direct_transactions' => false],
        ['code' => '1203', 'name' => 'العقارات الاستثمارية', 'name_ar' => 'العقارات الاستثمارية', 'account_type' => 'asset', 'parent' => '12', 'description' => 'أصول مشتراة لغرض الاستثمار وليس للاستخدام الذي يساهم في الأنشطة التشغيلية', 'allows_direct_transactions' => false],
        ['code' => '2', 'name' => 'الالتزامات', 'name_ar' => 'الالتزامات', 'account_type' => 'liability', 'description' => null, 'allows_direct_transactions' => false, 'match_codes' => ['2', '2000'], 'match_fragments' => ['الالتزامات', 'الخصوم', 'Liabilities']],
        ['code' => '21', 'name' => 'الالتزامات المتداولة', 'name_ar' => 'الالتزامات المتداولة', 'account_type' => 'liability', 'parent' => '2', 'description' => null, 'allows_direct_transactions' => false, 'match_codes' => ['2.10', '2010'], 'match_fragments' => ['الالتزامات المتداولة', 'الخصوم المتداولة', 'Current Liabilities']],
        ['code' => '2101', 'name' => 'الدائنون', 'name_ar' => 'الدائنون', 'account_type' => 'liability', 'parent' => '21', 'description' => 'مبالغ مستحقة لحسابات الموردين (بالأجل)', 'allows_direct_transactions' => false, 'match_codes' => ['2.1', '2100'], 'match_fragments' => ['الدائنون', 'الموردين', 'ذمم دائنة', 'Payable']],
        ['code' => '2102', 'name' => 'مصروفات مستحقة', 'name_ar' => 'مصروفات مستحقة', 'account_type' => 'liability', 'parent' => '21', 'description' => 'مصروفات مستحقة على المنشأة لم يتم سدادها أو تسجيلها بعد', 'allows_direct_transactions' => false, 'match_codes' => ['2.4', '2400'], 'match_fragments' => ['مصروفات مستحقة', 'Accrued Expenses']],
        ['code' => '2103', 'name' => 'الرواتب المستحقة', 'name_ar' => 'الرواتب المستحقة', 'account_type' => 'liability', 'parent' => '21', 'description' => 'رواتب مستحقة على المنشأة لم يتم سدادها بعد', 'allows_direct_transactions' => false],
        ['code' => '2104', 'name' => 'قروض قصيرة الأجل', 'name_ar' => 'قروض قصيرة الأجل', 'account_type' => 'liability', 'parent' => '21', 'description' => 'قروض متوقع سداده خلال عام أو فترة مالية أيهما أطول', 'allows_direct_transactions' => false],
        ['code' => '2105', 'name' => 'ضريبة القيمة المضافة المستحقة', 'name_ar' => 'ضريبة القيمة المضافة المستحقة', 'account_type' => 'liability', 'parent' => '21', 'description' => 'ضريبة القيمة المضافة مستحقة الدفع لهيئة الزكاة والدخل', 'allows_direct_transactions' => false, 'match_codes' => ['2.3', '2200'], 'match_fragments' => ['ضريبة القيمة المضافة المستحقة', 'ضريبة المخرجات', 'ضريبة المدخلات', 'VAT Payable', 'Output VAT', 'Input VAT']],
        ['code' => '2106', 'name' => 'الضرائب المستحقة', 'name_ar' => 'الضرائب المستحقة', 'account_type' => 'liability', 'parent' => '21', 'description' => 'ضريبة الدخل المستحقة عن الشركات الأجنبية', 'allows_direct_transactions' => false],
        ['code' => '2107', 'name' => 'إيرادات غير مكتسبة', 'name_ar' => 'إيرادات غير مكتسبة', 'account_type' => 'liability', 'parent' => '21', 'description' => 'مبالغ حصلت عليها المنشأة قبل تسليم البضاعة أو تقديم الخدمة', 'allows_direct_transactions' => false],
        ['code' => '2108', 'name' => 'مستحقات المؤسسة العامة للتأمينات الاجتماعية', 'name_ar' => 'مستحقات المؤسسة العامة للتأمينات الاجتماعية', 'account_type' => 'liability', 'parent' => '21', 'description' => 'مبالغ مستحقة للمؤسسة العامة للتأمينات الاجتماعية', 'allows_direct_transactions' => false],
        ['code' => '2109', 'name' => 'مجمع الاستهلاك', 'name_ar' => 'مجمع الاستهلاك', 'account_type' => 'liability', 'parent' => '21', 'description' => 'مجمع استهلاك الأصول', 'allows_direct_transactions' => false],
        ['code' => '210901', 'name' => 'مجمع استهلاك المباني', 'name_ar' => 'مجمع استهلاك المباني', 'account_type' => 'liability', 'parent' => '2109', 'description' => 'مجمع استهلاك المباني', 'allows_direct_transactions' => false],
        ['code' => '210902', 'name' => 'مجمع استهلاك المعدات', 'name_ar' => 'مجمع استهلاك المعدات', 'account_type' => 'liability', 'parent' => '2109', 'description' => 'مجمع استهلاك المعدات', 'allows_direct_transactions' => false],
        ['code' => '210903', 'name' => 'مجمع استهلاك أجهزة مكتبية وطابعات', 'name_ar' => 'مجمع استهلاك أجهزة مكتبية وطابعات', 'account_type' => 'liability', 'parent' => '2109', 'description' => 'مجمع استهلاك أجهزة مكتبية وطابعات', 'allows_direct_transactions' => false],
        ['code' => '22', 'name' => 'التزامات غير متداولة', 'name_ar' => 'التزامات غير متداولة', 'account_type' => 'liability', 'parent' => '2', 'description' => null, 'allows_direct_transactions' => false, 'match_codes' => ['2.20', '2020'], 'match_fragments' => ['التزامات غير متداولة', 'الخصوم طويلة الأجل', 'Long-term Liabilities']],
        ['code' => '2201', 'name' => 'قروض طويلة أجل', 'name_ar' => 'قروض طويلة أجل', 'account_type' => 'liability', 'parent' => '22', 'description' => 'قروض طويلة الأجل مستحق سدادها خلال أكثر من عام أو فترة مالية أيهما أطول', 'allows_direct_transactions' => false, 'match_codes' => ['2.2'], 'match_fragments' => ['قروض طويلة أجل', 'قروض طويلة الأجل', 'Loans']],
        ['code' => '2202', 'name' => 'مخصص مكافأة نهاية الخدمة', 'name_ar' => 'مخصص مكافأة نهاية الخدمة', 'account_type' => 'liability', 'parent' => '22', 'description' => 'مخصص مكافأة نهاية الخدمة للموظفين', 'allows_direct_transactions' => false],
        ['code' => '3', 'name' => 'حقوق الملكية', 'name_ar' => 'حقوق الملكية', 'account_type' => 'equity', 'description' => null, 'allows_direct_transactions' => false, 'match_codes' => ['5', '3000'], 'match_fragments' => ['حقوق الملكية', 'حقوق الملاك', 'Equity']],
        ['code' => '31', 'name' => 'رأس المال', 'name_ar' => 'رأس المال', 'account_type' => 'equity', 'parent' => '3', 'description' => 'رأس المال المصدر', 'allows_direct_transactions' => false, 'match_codes' => ['5.1', '3100'], 'match_fragments' => ['رأس المال', 'Capital']],
        ['code' => '3101', 'name' => 'رأس المال المسجل', 'name_ar' => 'رأس المال المسجل', 'account_type' => 'equity', 'parent' => '31', 'description' => 'رأس المال المسجل في السجل التجاري', 'allows_direct_transactions' => false],
        ['code' => '3102', 'name' => 'رأس المال الإضافي المدفوع', 'name_ar' => 'رأس المال الإضافي المدفوع', 'account_type' => 'equity', 'parent' => '31', 'description' => 'رأس المال إضافي مدفوع من قبل المستثمرين لزيادة حقوق الملكية', 'allows_direct_transactions' => false],
        ['code' => '32', 'name' => 'حقوق ملكية أخرى', 'name_ar' => 'حقوق ملكية أخرى', 'account_type' => 'equity', 'parent' => '3', 'description' => 'حقوق الملاك الأخرى', 'allows_direct_transactions' => false],
        ['code' => '3201', 'name' => 'أرصدة افتتاحية', 'name_ar' => 'أرصدة افتتاحية', 'account_type' => 'equity', 'parent' => '32', 'description' => 'الأرصدة الافتتاحية', 'allows_direct_transactions' => false],
        ['code' => '33', 'name' => 'احتياطيات', 'name_ar' => 'احتياطيات', 'account_type' => 'equity', 'parent' => '3', 'description' => 'حقوق الملاك الأخرى', 'allows_direct_transactions' => false],
        ['code' => '3301', 'name' => 'احتياطي نظامي', 'name_ar' => 'احتياطي نظامي', 'account_type' => 'equity', 'parent' => '33', 'description' => 'تجنيب 10% من صافي الربح حتى يصل إلى 30% من رأس المال حسب نظام الشركات', 'allows_direct_transactions' => false],
        ['code' => '3302', 'name' => 'احتياطي ترجمة عملات أجنبية', 'name_ar' => 'احتياطي ترجمة عملات أجنبية', 'account_type' => 'equity', 'parent' => '33', 'description' => 'احتياطي لتغطية الفرق بين سعر الصرف عند تسجيل الأصول أو الالتزامات عن سعر الصرف وقت السداد', 'allows_direct_transactions' => false],
        ['code' => '34', 'name' => 'الأرباح المبقاة (أو الخسائر)', 'name_ar' => 'الأرباح المبقاة (أو الخسائر)', 'account_type' => 'equity', 'parent' => '3', 'description' => 'أرباح/خسائر مبقاة', 'allows_direct_transactions' => false, 'match_codes' => ['5.2', '3200'], 'match_fragments' => ['الأرباح المبقاة', 'Retained Earnings']],
        ['code' => '3401', 'name' => 'الأرباح والخسائر العاملة', 'name_ar' => 'الأرباح والخسائر العاملة', 'account_type' => 'equity', 'parent' => '34', 'description' => 'صافي الربح أو الخسارة للفترة المالية الحالية', 'allows_direct_transactions' => false],
        ['code' => '3402', 'name' => 'الأرباح المبقاة (أو الخسائر)', 'name_ar' => 'الأرباح المبقاة (أو الخسائر)', 'account_type' => 'equity', 'parent' => '34', 'description' => 'أرباح مبقاة لغرض إعادة استثمارها في أعمال المنشأة', 'allows_direct_transactions' => false],
        ['code' => '4', 'name' => 'الإيرادات', 'name_ar' => 'الإيرادات', 'account_type' => 'revenue', 'description' => null, 'allows_direct_transactions' => false, 'match_codes' => ['4000'], 'match_fragments' => ['الإيرادات', 'Revenue']],
        ['code' => '41', 'name' => 'الإيرادات التشغيلية', 'name_ar' => 'الإيرادات التشغيلية', 'account_type' => 'revenue', 'parent' => '4', 'description' => 'المبيعات', 'allows_direct_transactions' => false, 'match_codes' => ['3.10', '4010'], 'match_fragments' => ['الإيرادات التشغيلية', 'Operating Revenue']],
        ['code' => '4101', 'name' => 'إيرادات المبيعات/ الخدمات', 'name_ar' => 'إيرادات المبيعات/ الخدمات', 'account_type' => 'revenue', 'parent' => '41', 'description' => 'الدخل الناتج من بيع سلعة أو تقديم خدمة', 'allows_direct_transactions' => false, 'match_codes' => ['3.1', '4100', '4200'], 'match_fragments' => ['إيرادات المبيعات', 'إيرادات الخدمات', 'المبيعات', 'Sales']],
        ['code' => '42', 'name' => 'الإيرادات غير التشغيلية', 'name_ar' => 'الإيرادات غير التشغيلية', 'account_type' => 'revenue', 'parent' => '4', 'description' => 'الإيرادات الأخرى', 'allows_direct_transactions' => false, 'match_codes' => ['3.20', '4020'], 'match_fragments' => ['الإيرادات غير التشغيلية', 'Non-operating Revenue']],
        ['code' => '4201', 'name' => 'إيرادات أخرى', 'name_ar' => 'إيرادات أخرى', 'account_type' => 'revenue', 'parent' => '42', 'description' => 'إيراد نتج من أنشطة أخرى للمنشأة غير النشاط الأساسي', 'allows_direct_transactions' => false, 'match_codes' => ['3.3', '4300'], 'match_fragments' => ['إيرادات أخرى', 'Other Income']],
        ['code' => '5', 'name' => 'المصاريف', 'name_ar' => 'المصاريف', 'account_type' => 'expense', 'description' => null, 'allows_direct_transactions' => false, 'match_codes' => ['6000'], 'match_fragments' => ['المصاريف', 'Expenses']],
        ['code' => '51', 'name' => 'التكاليف المباشرة', 'name_ar' => 'التكاليف المباشرة', 'account_type' => 'expense', 'parent' => '5', 'description' => 'التكلفة المباشرة', 'allows_direct_transactions' => false, 'match_codes' => ['4.40', '6040'], 'match_fragments' => ['التكاليف المباشرة', 'تكلفة المبيعات']],
        ['code' => '5101', 'name' => 'تكلفة البضاعة المباعة', 'name_ar' => 'تكلفة البضاعة المباعة', 'account_type' => 'cogs', 'parent' => '51', 'description' => 'تكلفة البضاعة المباعة', 'allows_direct_transactions' => false, 'match_codes' => ['4.4', '5100', '5000'], 'match_fragments' => ['تكلفة البضاعة المباعة', 'COGS', 'Cost of Goods Sold']],
        ['code' => '5102', 'name' => 'رواتب وأجور', 'name_ar' => 'رواتب وأجور', 'account_type' => 'expense', 'parent' => '51', 'description' => 'رواتب وأجور الموظفين العاملين في النشاط الأساسي للمنشأة', 'allows_direct_transactions' => false],
        ['code' => '5103', 'name' => 'عمولات البيع', 'name_ar' => 'عمولات البيع', 'account_type' => 'expense', 'parent' => '51', 'description' => 'عمولات البيع', 'allows_direct_transactions' => false],
        ['code' => '5104', 'name' => 'شحن وتخليص جمركي', 'name_ar' => 'شحن وتخليص جمركي', 'account_type' => 'expense', 'parent' => '51', 'description' => 'شحن وتخليص جمركي للبضاعة المستوردة من الخارج', 'allows_direct_transactions' => false],
        ['code' => '52', 'name' => 'التكاليف التشغيلية', 'name_ar' => 'التكاليف التشغيلية', 'account_type' => 'expense', 'parent' => '5', 'description' => 'تكاليف تشغيلية', 'allows_direct_transactions' => false, 'match_codes' => ['4.10', '6010'], 'match_fragments' => ['التكاليف التشغيلية', 'المصروفات الإدارية', 'Operating Expenses']],
        ['code' => '5201', 'name' => 'الرواتب والرسوم الإدارية', 'name_ar' => 'الرواتب والرسوم الإدارية', 'account_type' => 'expense', 'parent' => '52', 'description' => 'رواتب وأجور الموظفين الإداريين', 'allows_direct_transactions' => false, 'match_codes' => ['4.1', '6100', '5300'], 'match_fragments' => ['الرواتب والرسوم الإدارية', 'رواتب وأجور إدارية', 'رواتب', 'Salaries']],
        ['code' => '5202', 'name' => 'تأمين طبي', 'name_ar' => 'تأمين طبي', 'account_type' => 'expense', 'parent' => '52', 'description' => 'تأمين طبي وعلاج', 'allows_direct_transactions' => false],
        ['code' => '5203', 'name' => 'مصاريف تسويقية ودعائية', 'name_ar' => 'مصاريف تسويقية ودعائية', 'account_type' => 'expense', 'parent' => '52', 'description' => 'مصاريف تسويقية ودعائية', 'allows_direct_transactions' => false, 'match_codes' => ['4.6', '6500', '5500'], 'match_fragments' => ['مصاريف تسويقية', 'تسويق', 'Marketing']],
        ['code' => '5204', 'name' => 'مصاريف الإيجار', 'name_ar' => 'مصاريف الإيجار', 'account_type' => 'expense', 'parent' => '52', 'description' => 'إيجار المكتب', 'allows_direct_transactions' => false, 'match_codes' => ['4.2', '6200', '5200'], 'match_fragments' => ['مصاريف الإيجار', 'إيجار', 'Rent']],
        ['code' => '5205', 'name' => 'عمولات وحوافز', 'name_ar' => 'عمولات وحوافز', 'account_type' => 'expense', 'parent' => '52', 'description' => 'مكافآت وحوافز للموظفين الإداريين', 'allows_direct_transactions' => false],
        ['code' => '5206', 'name' => 'تذاكر سفر', 'name_ar' => 'تذاكر سفر', 'account_type' => 'expense', 'parent' => '52', 'description' => 'مصاريف سفر', 'allows_direct_transactions' => false],
        ['code' => '5207', 'name' => 'التأمينات الاجتماعية', 'name_ar' => 'التأمينات الاجتماعية', 'account_type' => 'expense', 'parent' => '52', 'description' => 'نسبة التأمينات الاجتماعية تدفع شهرياً', 'allows_direct_transactions' => false],
        ['code' => '5208', 'name' => 'الرسوم الحكومية', 'name_ar' => 'الرسوم الحكومية', 'account_type' => 'expense', 'parent' => '52', 'description' => 'مثل رسوم تجديد السجل التجاري والبلدية وختم الغرفة التجارية', 'allows_direct_transactions' => false],
        ['code' => '5209', 'name' => 'رسوم واشتراكات', 'name_ar' => 'رسوم واشتراكات', 'account_type' => 'expense', 'parent' => '52', 'description' => 'رسوم اشتراكات', 'allows_direct_transactions' => false],
        ['code' => '5210', 'name' => 'مصاريف خدمات المكتب', 'name_ar' => 'مصاريف خدمات المكتب', 'account_type' => 'expense', 'parent' => '52', 'description' => 'فواتير الماء والكهرباء والهاتف والانترنت', 'allows_direct_transactions' => false, 'match_codes' => ['4.5', '6400'], 'match_fragments' => ['مصاريف خدمات المكتب', 'رسوم خدمات المكتب', 'مرافق', 'Utilities']],
        ['code' => '5211', 'name' => 'مصاريف مكتبية ومطبوعات', 'name_ar' => 'مصاريف مكتبية ومطبوعات', 'account_type' => 'expense', 'parent' => '52', 'description' => 'قرطاسية وطباعة', 'allows_direct_transactions' => false],
        ['code' => '5212', 'name' => 'مصاريف ضيافة', 'name_ar' => 'مصاريف ضيافة', 'account_type' => 'expense', 'parent' => '52', 'description' => 'ضيافة ونظافة تخص المنشأة', 'allows_direct_transactions' => false],
        ['code' => '5213', 'name' => 'عمولات بنكية', 'name_ar' => 'عمولات بنكية', 'account_type' => 'expense', 'parent' => '52', 'description' => 'رسوم بنكية عند تحويل من بنك محلي إلى بنك محلي آخر أو لطباعة كشف حساب مختوم', 'allows_direct_transactions' => false, 'match_codes' => ['4.31', '6600'], 'match_fragments' => ['عمولات بنكية', 'رسوم بنكية', 'Bank Charges']],
        ['code' => '5214', 'name' => 'مصاريف أخرى', 'name_ar' => 'مصاريف أخرى', 'account_type' => 'expense', 'parent' => '52', 'description' => 'مصاريف أخرى متنوعة', 'allows_direct_transactions' => false, 'match_codes' => ['4.3', '6300', '5600'], 'match_fragments' => ['مصاريف أخرى', 'مصروفات متنوعة', 'Miscellaneous']],
        ['code' => '5215', 'name' => 'مصاريف الإهلاك', 'name_ar' => 'مصاريف الإهلاك', 'account_type' => 'expense', 'parent' => '52', 'description' => 'إهلاك الأصول الثابتة', 'allows_direct_transactions' => false],
        ['code' => '521501', 'name' => 'مصروف إهلاك المباني', 'name_ar' => 'مصروف إهلاك المباني', 'account_type' => 'expense', 'parent' => '5215', 'description' => 'مصروف إهلاك المباني', 'allows_direct_transactions' => false],
        ['code' => '521502', 'name' => 'مصروف إهلاك المعدات', 'name_ar' => 'مصروف إهلاك المعدات', 'account_type' => 'expense', 'parent' => '5215', 'description' => 'مصروف إهلاك المعدات', 'allows_direct_transactions' => false],
        ['code' => '521503', 'name' => 'مصروف إهلاك أجهزة مكتبية وطابعات', 'name_ar' => 'مصروف إهلاك أجهزة مكتبية وطابعات', 'account_type' => 'expense', 'parent' => '5215', 'description' => 'مصروف إهلاك أجهزة مكتبية وطابعات', 'allows_direct_transactions' => false],
        ['code' => '5219', 'name' => 'مصروف نقل ومواصلات', 'name_ar' => 'مصروف نقل ومواصلات', 'account_type' => 'expense', 'parent' => '52', 'description' => 'مصروف نقل ومواصلات (بنزين ، أجرة)', 'allows_direct_transactions' => false],
        ['code' => '53', 'name' => 'مصاريف غير التشغيلية', 'name_ar' => 'مصاريف غير التشغيلية', 'account_type' => 'expense', 'parent' => '5', 'description' => 'تكاليف غير تشغيلية', 'allows_direct_transactions' => false, 'match_codes' => ['4.30', '6030'], 'match_fragments' => ['مصاريف غير التشغيلية', 'المصروفات التمويلية', 'Non-operating Expenses']],
        ['code' => '5301', 'name' => 'الزكاة', 'name_ar' => 'الزكاة', 'account_type' => 'expense', 'parent' => '53', 'description' => 'زكاة تدفع لهيئة الزكاة والدخل', 'allows_direct_transactions' => false],
        ['code' => '5302', 'name' => 'الضرائب', 'name_ar' => 'الضرائب', 'account_type' => 'expense', 'parent' => '53', 'description' => 'ضريبة الدخل تدفع لهيئة الزكاة والدخل', 'allows_direct_transactions' => false],
        ['code' => '5303', 'name' => 'ترجمة عملات أجنبية', 'name_ar' => 'ترجمة عملات أجنبية', 'account_type' => 'expense', 'parent' => '53', 'description' => 'الربح أو الخسارة من ترجمة عملات أجنبية', 'allows_direct_transactions' => false],
        ['code' => '5304', 'name' => 'فوائد', 'name_ar' => 'فوائد', 'account_type' => 'expense', 'parent' => '53', 'description' => 'فوائد بنكية', 'allows_direct_transactions' => false],
    ];

    private const DISPLAY_ACCOUNT_TYPES = [
        '1' => 'الاصول',
        '11' => 'الأصول المتداولة',
        '1101' => 'النقدية ومافي حكمها',
        '110101' => 'النقدية ومافي حكمها',
        '110102' => 'عهد نقدية',
        '1102' => 'حساب البنك',
        '110201' => 'حساب البنك',
        '1103' => 'المدينون',
        '1106' => 'المخزون',
        '12' => 'الأصول غير المتداولة',
        '1201' => 'عقارات وآلات ومعدات',
        '120101' => 'عقارات وآلات ومعدات',
        '120102' => 'عقارات وآلات ومعدات',
        '120103' => 'عقارات وآلات ومعدات',
        '120104' => 'عقارات وآلات ومعدات',
        '1202' => 'أصول غير ملموسة',
        '1203' => 'أصول غير متداولة أخرى',
        '2' => 'الالتزامات',
        '21' => 'الالتزامات المتداولة',
        '2101' => 'الدائنون',
        '2102' => 'مصاريف مستحقة',
        '2103' => 'الرواتب والمبالغ المستحقة للموظفين',
        '2104' => 'قروض قصيرة الأجل',
        '2105' => 'ضريبة القيمة المضافة المستحقة',
        '2106' => 'الضرائب المستحقة',
        '2107' => 'الإيرادات المقدمة',
        '2108' => 'التزامات متداولة أخرى',
        '2109' => 'مجمع الاستهلاك',
        '210901' => 'مجمع الاستهلاك',
        '210902' => 'مجمع الاستهلاك',
        '210903' => 'مجمع الاستهلاك',
        '22' => 'الالتزامات غير المتداولة',
        '2201' => 'قروض طويلة الأجل',
        '2202' => 'مخصص مكافأة نهاية الخدمة',
        '3' => 'حقوق الملاك',
        '31' => 'رأس المال المصدر',
        '3101' => 'رأس المال',
        '3102' => 'رأس المال الإضافي المدفوع',
        '32' => 'حقوق الملاك الأخرى',
        '3201' => 'حقوق ملكية أخرى',
        '33' => 'حقوق الملاك الأخرى',
        '3301' => 'الاحتياطيات',
        '3302' => 'الاحتياطيات',
        '34' => 'أرباح/خسائر مبقاة',
        '3401' => 'الأرباح المبقاة (أو الخسائر)',
        '3402' => 'الأرباح المبقاة (أو الخسائر)',
        '4' => 'الايرادات',
        '41' => 'المبيعات',
        '4101' => 'المبيعات',
        '42' => 'الإيرادات الأخرى',
        '4201' => 'إيرادات أخرى',
        '5' => 'المصاريف',
        '51' => 'التكلفة المباشرة',
        '5101' => 'تكلفة المبيعات',
        '5102' => 'تكاليف مباشرة أخرى',
        '5103' => 'تكاليف مباشرة أخرى',
        '5104' => 'تكاليف مباشرة أخرى',
        '52' => 'تكاليف تشغيلية',
        '5201' => 'الرواتب',
        '5202' => 'مصاريف عمومية وإدارية',
        '5203' => 'مصاريف تسويقية',
        '5204' => 'مصاريف عمومية وإدارية',
        '5205' => 'مكافآت وحوافز',
        '5206' => 'مصاريف عمومية وإدارية',
        '5207' => 'مصاريف عمومية وإدارية',
        '5208' => 'مصاريف عمومية وإدارية',
        '5209' => 'مصاريف عمومية وإدارية',
        '5210' => 'مصاريف عمومية وإدارية',
        '5211' => 'مصاريف عمومية وإدارية',
        '5212' => 'مصاريف عمومية وإدارية',
        '5213' => 'مصاريف عمومية وإدارية',
        '5214' => 'مصاريف عمومية وإدارية',
        '5215' => 'مصاريف الاستهلاك',
        '521501' => 'مصاريف الاستهلاك',
        '521502' => 'مصاريف الاستهلاك',
        '521503' => 'مصاريف الاستهلاك',
        '5219' => 'مصاريف عمومية وإدارية',
        '53' => 'تكاليف غير تشغيلية',
        '5301' => 'الزكاة',
        '5302' => 'ضرائب',
        '5303' => 'ترجمة عملات أجنبية',
        '5304' => 'مصروف فوائد',
    ];

    public function ensureBaseAccounts(int|Company $company): Collection
    {
        $companyId = $company instanceof Company ? (int) $company->id : (int) $company;
        $existingAccounts = Account::where('company_id', $companyId)->get();
        $accountsByCode = $existingAccounts->keyBy('code');
        $synced = collect();

        foreach (self::BASE_DEFINITIONS as $definition) {
            $parentId = null;

            if (isset($definition['parent'])) {
                $parentId = $synced->get($definition['parent'])?->id
                    ?? $accountsByCode->get($definition['parent'])?->id;
            }

            $account = $accountsByCode->get($definition['code'])
                ?? $this->findLegacyAccount($companyId, $definition, $existingAccounts);

            $payload = [
                'code' => $definition['code'],
                'name' => $definition['name'],
                'name_ar' => $definition['name_ar'],
                'account_type' => $definition['account_type'],
                'display_account_type' => $this->displayAccountTypeForCode($definition['code'], $definition['account_type']),
                'parent_id' => $parentId,
                'allows_direct_transactions' => (bool) ($definition['allows_direct_transactions'] ?? false),
                'is_active' => true,
                'is_system' => true,
                'description' => $definition['description'] ?? null,
            ];

            if ($account) {
                $account->fill($payload);
                $account->save();
            } else {
                $account = Account::create(array_merge($payload, [
                    'company_id' => $companyId,
                ]));
                $existingAccounts->push($account);
            }

            $accountsByCode->put($definition['code'], $account);
            $synced->put($definition['code'], $account);
        }

        return $synced;
    }

    public function synchronizeCompany(Company|int $company): void
    {
        $companyModel = $company instanceof Company
            ? $company->loadMissing(['customers', 'suppliers', 'products'])
            : Company::with(['customers', 'suppliers', 'products'])->findOrFail($company);

        $this->ensureBaseAccounts($companyModel);

        foreach ($companyModel->customers as $customer) {
            $this->syncCustomerAccount($customer);
        }

        foreach ($companyModel->suppliers as $supplier) {
            $this->syncSupplierAccount($supplier);
        }

        foreach ($companyModel->products as $product) {
            $this->syncProductAccounts($product);
        }
    }

    public function syncCustomerAccount(Customer $customer): Account
    {
        $roots = $this->ensureBaseAccounts((int) $customer->company_id);
        $account = $this->upsertLinkedAccount(
            companyId: (int) $customer->company_id,
            existingAccountId: $customer->account_id,
            code: '1103-C' . $customer->id,
            name: 'ذمة العميل - ' . $customer->name,
            nameAr: 'ذمة العميل - ' . ($customer->name_ar ?: $customer->name),
            type: 'asset',
            parentId: $roots->get('1103')?->id,
        );

        if ((int) $customer->account_id !== (int) $account->id) {
            $customer->forceFill(['account_id' => $account->id])->save();
        }

        return $account;
    }

    public function syncSupplierAccount(Supplier $supplier): Account
    {
        $roots = $this->ensureBaseAccounts((int) $supplier->company_id);
        $account = $this->upsertLinkedAccount(
            companyId: (int) $supplier->company_id,
            existingAccountId: $supplier->account_id,
            code: '2101-S' . $supplier->id,
            name: 'ذمة المورد - ' . $supplier->name,
            nameAr: 'ذمة المورد - ' . ($supplier->name_ar ?: $supplier->name),
            type: 'liability',
            parentId: $roots->get('2101')?->id,
        );

        if ((int) $supplier->account_id !== (int) $account->id) {
            $supplier->forceFill(['account_id' => $account->id])->save();
        }

        return $account;
    }

    public function syncProductAccounts(Product $product): Product
    {
        $roots = $this->ensureBaseAccounts((int) $product->company_id);

        $revenueAccount = $this->upsertLinkedAccount(
            companyId: (int) $product->company_id,
            existingAccountId: $product->revenue_account_id,
            code: '4101-P' . $product->id,
            name: 'إيرادات - ' . $product->name,
            nameAr: 'إيرادات - ' . ($product->name_ar ?: $product->name),
            type: 'revenue',
            parentId: $roots->get('4101')?->id,
        );

        $updates = [
            'revenue_account_id' => $revenueAccount->id,
        ];

        if ($product->type === 'product') {
            $inventoryAccount = $this->upsertLinkedAccount(
                companyId: (int) $product->company_id,
                existingAccountId: $product->inventory_account_id,
                code: '1106-P' . $product->id,
                name: 'مخزون - ' . $product->name,
                nameAr: 'مخزون - ' . ($product->name_ar ?: $product->name),
                type: 'asset',
                parentId: $roots->get('1106')?->id,
            );

            $cogsAccount = $this->upsertLinkedAccount(
                companyId: (int) $product->company_id,
                existingAccountId: $product->cogs_account_id,
                code: '5101-P' . $product->id,
                name: 'تكلفة البضاعة المباعة - ' . $product->name,
                nameAr: 'تكلفة البضاعة المباعة - ' . ($product->name_ar ?: $product->name),
                type: 'cogs',
                parentId: $roots->get('5101')?->id,
            );

            $updates['inventory_account_id'] = $inventoryAccount->id;
            $updates['cogs_account_id'] = $cogsAccount->id;
        } else {
            $updates['inventory_account_id'] = null;
            $updates['cogs_account_id'] = null;
        }

        $product->forceFill($updates)->save();

        return $product->fresh();
    }

    private function findLegacyAccount(int $companyId, array $definition, Collection $accounts): ?Account
    {
        $matchCodes = $definition['match_codes'] ?? [];
        if ($matchCodes !== []) {
            $matchedByCode = $accounts
                ->first(fn (Account $account) => (int) $account->company_id === $companyId && in_array($account->code, $matchCodes, true));

            if ($matchedByCode) {
                return $matchedByCode;
            }
        }

        foreach ($definition['match_fragments'] ?? [] as $fragment) {
            $matchedByName = $accounts->first(function (Account $account) use ($companyId, $definition, $fragment) {
                if ((int) $account->company_id !== $companyId || $account->account_type !== $definition['account_type']) {
                    return false;
                }

                return str_contains(mb_strtolower($account->name), mb_strtolower($fragment))
                    || str_contains(mb_strtolower((string) $account->name_ar), mb_strtolower($fragment));
            });

            if ($matchedByName) {
                return $matchedByName;
            }
        }

        return null;
    }

    private function upsertLinkedAccount(int $companyId, ?int $existingAccountId, string $code, string $name, string $nameAr, string $type, ?int $parentId): Account
    {
        $account = null;

        if ($existingAccountId) {
            $account = Account::where('company_id', $companyId)->find($existingAccountId);
        }

        $account ??= Account::where('company_id', $companyId)->where('code', $code)->first();

        $account ??= Account::where('company_id', $companyId)
            ->where('parent_id', $parentId)
            ->where('account_type', $type)
            ->where(function ($query) use ($name, $nameAr) {
                $query->where('name', $name)
                    ->orWhere('name_ar', $nameAr);
            })
            ->first();

        $payload = [
            'code' => $code,
            'name' => $name,
            'name_ar' => $nameAr,
            'account_type' => $type,
            'display_account_type' => $this->displayAccountTypeForCode($code, $type),
            'parent_id' => $parentId,
            'allows_direct_transactions' => false,
            'is_active' => true,
            'is_system' => true,
        ];

        if ($account) {
            $account->fill($payload);
            $account->save();

            return $account;
        }

        return Account::create(array_merge($payload, [
            'company_id' => $companyId,
        ]));
    }

    private function displayAccountTypeForCode(string $code, string $accountType): string
    {
        if (isset(self::DISPLAY_ACCOUNT_TYPES[$code])) {
            return self::DISPLAY_ACCOUNT_TYPES[$code];
        }

        if (str_starts_with($code, '1103-C')) {
            return 'المدينون';
        }

        if (str_starts_with($code, '2101-S')) {
            return 'الدائنون';
        }

        if (str_starts_with($code, '4101-P')) {
            return 'المبيعات';
        }

        if (str_starts_with($code, '1106-P')) {
            return 'المخزون';
        }

        if (str_starts_with($code, '5101-P')) {
            return 'تكلفة المبيعات';
        }

        return match ($accountType) {
            'asset' => 'الأصول',
            'liability' => 'الالتزامات',
            'equity' => 'حقوق الملكية',
            'revenue' => 'الإيرادات',
            'expense' => 'المصاريف',
            default => 'تكلفة المبيعات',
        };
    }
}
