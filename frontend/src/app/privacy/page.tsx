import type { Metadata } from 'next';

export const metadata: Metadata = {
  title: '隱私權條款',
  description: '婕樂纖仙女館隱私權條款，說明我們如何蒐集、使用及保護您的個人資料。',
  alternates: { canonical: '/privacy' },
};

export default function PrivacyPage() {
  return (
    <div className="max-w-[800px] mx-auto px-5 sm:px-6 lg:px-8 py-12 sm:py-16">
      <h1 className="text-3xl font-bold text-gray-900 mb-8">隱私權條款</h1>

      <div className="prose-article text-gray-700 space-y-8 text-[15px] leading-relaxed">
        <p>
          歡迎您使用「婕樂纖仙女館」（以下簡稱「本網站」），本網站由法芮可有限公司（統一編號：90445399）營運。我們非常重視您的隱私權，以下說明本網站如何蒐集、處理及利用您的個人資料。
        </p>

        <section>
          <h2 className="text-xl font-semibold text-gray-900 mb-3">一、個人資料的蒐集</h2>
          <p>當您使用本網站服務時，我們可能會蒐集以下個人資料：</p>
          <ul className="list-disc pl-5 space-y-1 mt-2">
            <li>姓名、電話、電子信箱</li>
            <li>收件地址</li>
            <li>付款相關資訊（透過第三方金流服務處理）</li>
            <li>瀏覽紀錄與 Cookie 資訊</li>
            <li>其他您主動提供的資料</li>
          </ul>
        </section>

        <section>
          <h2 className="text-xl font-semibold text-gray-900 mb-3">二、個人資料的使用目的</h2>
          <p>您的個人資料將用於以下目的：</p>
          <ul className="list-disc pl-5 space-y-1 mt-2">
            <li>處理訂單、出貨及售後服務</li>
            <li>提供客戶服務與回覆諮詢</li>
            <li>寄送商品資訊、優惠活動通知（經您同意）</li>
            <li>改善網站服務品質及使用者體驗</li>
            <li>遵守法律規定之必要用途</li>
          </ul>
        </section>

        <section>
          <h2 className="text-xl font-semibold text-gray-900 mb-3">三、Cookie 的使用</h2>
          <p>
            本網站使用 Cookie 技術以提升您的瀏覽體驗。Cookie 是一種小型文字檔案，儲存於您的瀏覽器中，用於記錄您的偏好設定及瀏覽行為。
          </p>
          <p className="mt-2">我們使用的 Cookie 類型包括：</p>
          <ul className="list-disc pl-5 space-y-1 mt-2">
            <li><strong>必要性 Cookie：</strong>維持網站基本功能運作（如購物車功能）</li>
            <li><strong>分析性 Cookie：</strong>協助我們了解網站使用情況以改善服務</li>
          </ul>
          <p className="mt-2">
            您可透過瀏覽器設定管理或刪除 Cookie，但部分功能可能因此無法正常運作。
          </p>
        </section>

        <section>
          <h2 className="text-xl font-semibold text-gray-900 mb-3">四、第三方服務</h2>
          <p>
            本網站使用綠界科技（ECPay）作為金流服務提供商，您的付款資訊將由綠界科技依其隱私權政策進行處理。本網站不會儲存您的信用卡號碼或銀行帳戶資訊。
          </p>
        </section>

        <section>
          <h2 className="text-xl font-semibold text-gray-900 mb-3">五、資料保護措施</h2>
          <p>
            我們採取適當的技術及組織措施保護您的個人資料，防止未經授權的存取、使用或洩漏。然而，網際網路傳輸無法保證絕對安全，我們將盡最大努力保護您的資料安全。
          </p>
        </section>

        <section>
          <h2 className="text-xl font-semibold text-gray-900 mb-3">六、您的權利</h2>
          <p>依據個人資料保護法，您享有以下權利：</p>
          <ul className="list-disc pl-5 space-y-1 mt-2">
            <li>查詢或請求閱覽您的個人資料</li>
            <li>請求補充或更正您的個人資料</li>
            <li>請求停止蒐集、處理或利用您的個人資料</li>
            <li>請求刪除您的個人資料</li>
          </ul>
          <p className="mt-2">
            如需行使上述權利，請來信至{' '}
            <a href="mailto:contact@freeco.cc" className="text-[#9F6B3E] hover:underline">
              contact@freeco.cc
            </a>
            ，我們將儘速為您處理。
          </p>
        </section>

        <section>
          <h2 className="text-xl font-semibold text-gray-900 mb-3">七、條款修訂</h2>
          <p>
            本網站保留隨時修訂本隱私權條款之權利。修訂後的條款將公布於本頁面，並自公布日起生效。建議您定期瀏覽本頁面以了解最新的隱私權政策。
          </p>
        </section>

        <section>
          <h2 className="text-xl font-semibold text-gray-900 mb-3">八、聯絡方式</h2>
          <p>若您對本隱私權條款有任何疑問，歡迎透過以下方式與我們聯繫：</p>
          <ul className="list-none space-y-1 mt-2">
            <li>公司名稱：法芮可有限公司</li>
            <li>
              電子信箱：
              <a href="mailto:contact@freeco.cc" className="text-[#9F6B3E] hover:underline">
                contact@freeco.cc
              </a>
            </li>
            <li>
              Instagram：
              <a
                href="https://www.instagram.com/pandorasdo/"
                target="_blank"
                rel="noopener noreferrer"
                className="text-[#9F6B3E] hover:underline"
              >
                @pandorasdo
              </a>
            </li>
          </ul>
        </section>

        <p className="text-sm text-gray-500 mt-8">最後更新日期：2026 年 4 月 12 日</p>
      </div>
    </div>
  );
}
