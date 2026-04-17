import type { Metadata } from 'next';

export const metadata: Metadata = {
  title: '服務條款',
  description: '婕樂纖仙女館服務條款，說明使用本網站及購物服務之相關規範與約定。',
  alternates: { canonical: '/terms' },
};

export default function TermsPage() {
  return (
    <div className="max-w-[800px] mx-auto px-5 sm:px-6 lg:px-8 py-12 sm:py-16">
      <h1 className="text-3xl font-bold text-gray-900 mb-8">服務條款</h1>

      <div className="prose-article text-gray-700 space-y-8 text-[15px] leading-relaxed">
        <p>
          歡迎您使用「婕樂纖仙女館」（以下簡稱「本網站」），本網站由法芮可有限公司（統一編號：90445399）營運。當您使用本網站所提供之各項服務時，即表示您已閱讀、瞭解並同意接受以下服務條款。
        </p>

        <section>
          <h2 className="text-xl font-semibold text-gray-900 mb-3">一、服務範圍</h2>
          <p>
            本網站為 JEROSSE 婕樂纖官方正品授權經銷商，提供健康美麗相關產品之線上購物服務。本網站所販售之商品皆為食品，非藥品，不具醫療療效。
          </p>
        </section>

        <section>
          <h2 className="text-xl font-semibold text-gray-900 mb-3">二、會員帳號</h2>
          <ul className="list-disc pl-5 space-y-1 mt-2">
            <li>您可透過 Google 或 LINE 帳號登入本網站，無需另行註冊。</li>
            <li>您有責任妥善保管帳號安全，不得將帳號提供予第三方使用。</li>
            <li>透過您的帳號所進行之一切行為，均視為您本人之行為。</li>
            <li>如發現帳號遭未經授權使用，請立即與我們聯繫。</li>
          </ul>
        </section>

        <section>
          <h2 className="text-xl font-semibold text-gray-900 mb-3">三、商品資訊與價格</h2>
          <ul className="list-disc pl-5 space-y-1 mt-2">
            <li>本網站之商品圖片、說明及價格均以網站當時顯示為準。</li>
            <li>本網站提供三階梯定價：單件零售價、組合價（2 件以上）、VIP 價（組合總額達新台幣 4,000 元以上），詳細說明請參考商品頁面。</li>
            <li>本網站保留隨時調整商品價格及促銷活動之權利，調整後之價格自公布時起生效。</li>
            <li>若因系統錯誤導致商品標價明顯異常，本網站保留取消該筆訂單之權利，並將全額退款。</li>
          </ul>
        </section>

        <section>
          <h2 className="text-xl font-semibold text-gray-900 mb-3">四、訂購與付款</h2>
          <ul className="list-disc pl-5 space-y-1 mt-2">
            <li>當您於本網站完成訂購程序後，即視為您提出購買要約。本網站於確認訂單內容及付款狀態後，始成立買賣契約。</li>
            <li>本網站透過綠界科技（ECPay）提供線上付款服務，支援信用卡及超商付款等方式。</li>
            <li>付款完成後，您將收到訂單確認通知。</li>
          </ul>
        </section>

        <section>
          <h2 className="text-xl font-semibold text-gray-900 mb-3">五、配送方式</h2>
          <ul className="list-disc pl-5 space-y-1 mt-2">
            <li>本網站提供宅配到府及超商取貨服務。</li>
            <li>訂單成立後，一般於 1–3 個工作天內出貨（不含假日）。</li>
            <li>配送範圍限台灣本島及離島地區。離島地區配送時間可能較長。</li>
            <li>如遇不可抗力因素（如天災、疫情等），配送時間可能延遲，本網站將盡速通知。</li>
          </ul>
        </section>

        <section>
          <h2 className="text-xl font-semibold text-gray-900 mb-3">六、退換貨政策</h2>
          <p>
            依據消費者保護法規定，您享有商品到貨七日內無條件退貨之權利。詳細退換貨規定請參閱{' '}
            <a href="/return-policy" className="text-[#9F6B3E] hover:underline">
              退換貨政策
            </a>
            。
          </p>
        </section>

        <section>
          <h2 className="text-xl font-semibold text-gray-900 mb-3">七、智慧財產權</h2>
          <p>
            本網站所有內容，包括但不限於文字、圖片、商標、標誌、頁面設計及程式碼，均受智慧財產權法律保護。未經本網站書面同意，不得以任何形式複製、轉載、散佈或修改。
          </p>
        </section>

        <section>
          <h2 className="text-xl font-semibold text-gray-900 mb-3">八、免責聲明</h2>
          <ul className="list-disc pl-5 space-y-1 mt-2">
            <li>本網站之商品皆為食品，效果因個人體質而異，不保證特定效果。</li>
            <li>本網站不對因網路中斷、系統故障或其他不可抗力因素所造成之損害負責。</li>
            <li>本網站所提供之連結至第三方網站，其內容與服務非本網站所控制，本網站不承擔相關責任。</li>
          </ul>
        </section>

        <section>
          <h2 className="text-xl font-semibold text-gray-900 mb-3">九、條款修訂</h2>
          <p>
            本網站保留隨時修訂本服務條款之權利。修訂後的條款將公布於本頁面，並自公布日起生效。建議您定期瀏覽本頁面以了解最新的服務條款。
          </p>
        </section>

        <section>
          <h2 className="text-xl font-semibold text-gray-900 mb-3">十、準據法與管轄法院</h2>
          <p>
            本服務條款之解釋與適用，以中華民國法律為準據法。因本條款所生之爭議，雙方合意以台灣台北地方法院為第一審管轄法院。
          </p>
        </section>

        <section>
          <h2 className="text-xl font-semibold text-gray-900 mb-3">十一、聯絡方式</h2>
          <p>若您對本服務條款有任何疑問，歡迎透過以下方式與我們聯繫：</p>
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

        <p className="text-sm text-gray-500 mt-8">最後更新日期：2026 年 4 月 18 日</p>
      </div>
    </div>
  );
}
